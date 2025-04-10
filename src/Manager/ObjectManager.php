<?php

namespace BenTools\MeilisearchOdm\Manager;

use App\ApiResource\CustomerPointBalance;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use BenTools\MeilisearchOdm\Event\PrePersistEvent;
use BenTools\MeilisearchOdm\Event\PreRemoveEvent;
use BenTools\MeilisearchOdm\Event\PreUpdateEvent;
use BenTools\MeilisearchOdm\Hydrater\Hydrater;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\CoordinatesTransformer;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\DateTimeTransformer;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\ManyToOneRelationTransformer;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\PropertyTransformerInterface;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\StringableTransformer;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use BenTools\MeilisearchOdm\Misc\UniqueList;
use BenTools\MeilisearchOdm\Repository\ObjectRepository;
use Meilisearch\Client;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use WeakMap;

use function array_column;
use function array_map;
use function BenTools\IterableFunctions\iterable;
use function BenTools\IterableFunctions\iterable_chunk;
use function Bentools\MeilisearchFilters\field;
use function BenTools\MeilisearchOdm\uniqueList;
use function BenTools\MeilisearchOdm\weakmap_objects;
use function BenTools\MeilisearchOdm\weakmap_values;
use function is_array;

final class ObjectManager
{
    private const array DEFAULT_OPTIONS = [
        'flushBatchSize' => PHP_INT_MAX,
        'flushTimeoutMs' => 900_000,
        'flushCheckIntervalMs' => 50,
    ];

    public readonly Hydrater $hydrater;

    /**
     * @var array<class-string, ObjectRepository>
     */
    private array $repositories;

    /**
     * @var array{flushBatchSize: int, flushTimeoutMs: int, flushCheckIntervalMs: int}
     */
    private readonly array $options;

    private bool $isFlushing = false;
    private array $pendingFlushTasks = [];
    public readonly LoadedObjects $loadedObjects;
    private(set) UnitOfWork $unitOfWork;

    /**
     * @param PropertyTransformerInterface[] $transformers
     * @param array{flushBatchSize?: int, flushTimeoutMs?: int, flushCheckIntervalMs?: int} $options
     */
    public function __construct(
        public readonly Client $meili = new Client('http://localhost:7700'),
        public readonly ClassMetadataRegistry $classMetadataRegistry = new ClassMetadataRegistry(),
        public readonly EventDispatcherInterface $eventDispatcher = new EventDispatcher(),
        public readonly PropertyAccessorInterface $propertyAccessor = new PropertyAccessor(),
        array $transformers = [new DateTimeTransformer(), new StringableTransformer(), new CoordinatesTransformer()],
        array $options = [],
    ) {
        $this->hydrater = new Hydrater(
            $this->classMetadataRegistry,
            $this->propertyAccessor,
            (fn (PropertyTransformerInterface ...$transformers) => $transformers)(
                new ManyToOneRelationTransformer($this),
                ...$transformers
            ),
        );
        $this->loadedObjects = new LoadedObjects($this->hydrater);
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults(self::DEFAULT_OPTIONS);
        $optionsResolver->setAllowedTypes('flushBatchSize', ['int']);
        $optionsResolver->setAllowedTypes('flushTimeoutMs', ['int']);
        $optionsResolver->setAllowedTypes('flushCheckIntervalMs', ['int']);
        $this->options = $optionsResolver->resolve($options);
        $this->unitOfWork = new UnitOfWork($this->loadedObjects);
    }

    public function getClassMetadata(string $className): ClassMetadata
    {
        return $this->classMetadataRegistry->getClassMetadata($className);
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @param string|int|array<string, mixed> $idOrCriteria
     * @return T
     */
    public function find(string $className, string|int|array $idOrCriteria): ?object
    {
        if (is_array($idOrCriteria)) {
            return $this->getRepository($className)->findOneBy($idOrCriteria);
        }

        return $this->getRepository($className)->find($idOrCriteria);
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @return ObjectRepository<T>
     */
    public function getRepository(string $className): ObjectRepository
    {
        return $this->repositories[$className] ??= (function (string $className) {
            // Ensures the class is registered as a Meili Document
            $this->classMetadataRegistry->getClassMetadata($className);

            return new ObjectRepository($this, $className);
        })(
            $className,
        );
    }

    public function persist(object $object, object ...$objects): void
    {
        $this->unitOfWork->scheduleUpsert($object, ...$objects);
    }

    public function remove(object $object, object ...$objects): void
    {
        $this->unitOfWork->scheduleDelete($object, ...$objects);
    }

    public function flush(): void
    {
        if ($this->isFlushing) {
            return; // Avoid recursive flush calls during event propagation
        }

        try {
            $this->isFlushing = true;
            $flushBatchSize = $this->options['flushBatchSize'];
            $this->unitOfWork->computeChangesets();

            CheckChangesetsAndFireEvents:
            $hash = $this->unitOfWork->hash;

            // Process Updates
            $updateMetadata = new WeakMap();
            $deleteMetadata = new WeakMap();
            foreach ($this->unitOfWork->changesets as $object => $changeset) {
                $metadata = $this->classMetadataRegistry->getClassMetadata($object::class);
                $updateMetadata[$object] = $metadata;
                $this->maybeFirePrePersistEvent($object);
                $this->maybeFirePreUpdateEvent($object);
            }
            foreach ($this->unitOfWork->removals as $object) {
                $metadata = $this->classMetadataRegistry->getClassMetadata($object::class);
                $deleteMetadata[$object] = $metadata;
                $this->maybeFirePreRemoveEvent($object);
            }

            // Check if changesets have changed during events
            $this->unitOfWork->computeChangesets();
            if ($this->unitOfWork->hash !== $hash) {
                goto CheckChangesetsAndFireEvents;
            }

            $tasks = [];
            foreach (uniqueList(weakmap_values($updateMetadata)) as $metadata) {
                $documents = iterable(weakmap_objects($this->unitOfWork->changesets))
                    ->filter(fn (object $object) => $updateMetadata[$object] === $metadata)
                    ->map(fn (object $object) => $this->unitOfWork->changesets[$object]->newDocument)
                ;

                foreach (self::getItemsByBatches($documents, $flushBatchSize) as $documents) {
                    $docs = [...$documents];
                    $tasks[] = $this->meili->index($metadata->indexUid)->updateDocuments($docs);
                }
            }

            // Process Deletions
            foreach (uniqueList(weakmap_values($deleteMetadata)) as $metadata) {
                $scheduledDeletions = iterable($this->unitOfWork->removals)
                    ->filter(fn (object $object) => $deleteMetadata[$object] === $metadata)
                ;
                foreach (self::getItemsByBatches($scheduledDeletions, $flushBatchSize) as $objects) {
                    $tasks[] = $this->meili->index($metadata->indexUid)->deleteDocuments([
                        'filter' => (string) field($metadata->primaryKey)->isIn(
                            iterable($objects)
                                ->map(fn (object $object) => $this->hydrater->getIdFromObject($object))
                                ->asArray(),
                        ),
                    ]);
                }
            }

            $this->meili->waitForTasks(
                array_column($tasks, 'taskUid'),
                $this->options['flushTimeoutMs'],
                $this->options['flushCheckIntervalMs'],
            );

            // Update identity map
            foreach (weakmap_objects($this->unitOfWork->changesets) as $object) {
                $this->loadedObjects->rememberState($object, $this->unitOfWork->changesets[$object]->newDocument);
            }

            // Clear unit of work
            $this->unitOfWork = new UnitOfWork($this->loadedObjects);
        } finally {
            $this->isFlushing = false;
        }
    }

    private function maybeFirePrePersistEvent(object $object): void
    {
        if (UnitOfWork::CREATE !== $this->unitOfWork->getPendingOperation($object)) {
            return;
        }
        if ($this->unitOfWork->hasFiredEvent($object, PrePersistEvent::class)) {
            return;
        }
        $event = new PrePersistEvent($object, $this);
        $metadata = $this->classMetadataRegistry->getClassMetadata($object::class);
        foreach ($metadata->listeners[PrePersistEvent::class] ?? [] as $listener) {
            $listener->invoke($object, [$event]);
        }
        $this->eventDispatcher->dispatch($event);
        $this->unitOfWork->addFiredEvent($object, PrePersistEvent::class);
    }

    private function maybeFirePreUpdateEvent(object $object): void
    {
        if (UnitOfWork::UPDATE !== $this->unitOfWork->getPendingOperation($object)) {
            return;
        }
        if ($this->unitOfWork->hasFiredEvent($object, PreUpdateEvent::class)) {
            return;
        }
        $event = new PreUpdateEvent($object, $this);
        $metadata = $this->classMetadataRegistry->getClassMetadata($object::class);
        foreach ($metadata->listeners[PreUpdateEvent::class] ?? [] as $listener) {
            $listener->invoke($object, [$event]);
        }
        $this->eventDispatcher->dispatch($event);
        $this->unitOfWork->addFiredEvent($object, PreUpdateEvent::class);
    }

    private function maybeFirePreRemoveEvent(object $object): void
    {
        if (UnitOfWork::DELETE !== $this->unitOfWork->getPendingOperation($object)) {
            return;
        }
        if ($this->unitOfWork->hasFiredEvent($object, PreRemoveEvent::class)) {
            return;
        }
        $event = new PreRemoveEvent($object, $this);
        $metadata = $this->classMetadataRegistry->getClassMetadata($object::class);
        foreach ($metadata->listeners[PreRemoveEvent::class] ?? [] as $listener) {
            $listener->invoke($object, [$event]);
        }
        $this->eventDispatcher->dispatch($event);
        $this->unitOfWork->addFiredEvent($object, PreRemoveEvent::class);
    }

    /**
     * @return iterable<iterable<object>>
     */
    private static function getItemsByBatches(iterable $items, int $batchSize): iterable
    {
        if (PHP_INT_MAX === $batchSize) {
            return [$items];
        }

        return iterable_chunk($items, $batchSize);
    }
}
