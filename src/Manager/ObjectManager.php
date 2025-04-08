<?php

namespace BenTools\MeilisearchOdm\Manager;

use BenTools\MeilisearchOdm\Event\PrePersistEvent;
use BenTools\MeilisearchOdm\Event\PreUpdateEvent;
use BenTools\MeilisearchOdm\Hydrater\Hydrater;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\CoordinatesTransformer;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\DateTimeTransformer;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\PropertyTransformerInterface;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\StringableTransformer;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use BenTools\MeilisearchOdm\Repository\ObjectRepository;
use Meilisearch\Client;
use Meilisearch\Exceptions\TimeOutException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use WeakMap;

use function array_column;
use function array_shift;
use function BenTools\IterableFunctions\iterable;
use function BenTools\IterableFunctions\iterable_chunk;
use function Bentools\MeilisearchFilters\field;
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

    /**
     * @param PropertyTransformerInterface[] $transformers
     * @param array{flushBatchSize?: int, flushTimeoutMs?: int, flushCheckIntervalMs?: int} $options
     */
    public function __construct(
        public readonly Client $meili = new Client('http://localhost:7700'),
        public readonly ClassMetadataRegistry $classMetadataRegistry = new ClassMetadataRegistry(),
        public readonly EventDispatcherInterface $eventDispatcher = new EventDispatcher(),
        PropertyAccessorInterface $propertyAccessor = new PropertyAccessor(),
        array $transformers = [new DateTimeTransformer(), new StringableTransformer(), new CoordinatesTransformer()],
        array $options = [],
    ) {
        $this->hydrater = new Hydrater(
            $this,
            $propertyAccessor,
            (fn (PropertyTransformerInterface ...$transformers) => $transformers)(...$transformers),
        );
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults(self::DEFAULT_OPTIONS);
        $optionsResolver->setAllowedTypes('flushBatchSize', ['int']);
        $optionsResolver->setAllowedTypes('flushTimeoutMs', ['int']);
        $optionsResolver->setAllowedTypes('flushCheckIntervalMs', ['int']);
        $this->options = $optionsResolver->resolve($options);
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
        foreach ([$object, ...$objects] as $object) {
            $repository = $this->getRepository($object::class);
            $repository->identityMap->scheduleUpsert($object);
        }
    }

    public function remove(object $object, object ...$objects): void
    {
        foreach ([$object, ...$objects] as $object) {
            $repository = $this->getRepository($object::class);
            $repository->identityMap->scheduleDeletion($object);
        }
    }

    public function flush(): void
    {
        // This is done in case `flush()` is called during an ongoing `flush()` (i.e. PrePersist event): 2nd flush will be queued
        $this->pendingFlushTasks[] = [$this, 'doFlush'];
        if (!$this->isFlushing) {
            while ($task = array_shift($this->pendingFlushTasks)) {
                $task();
            }
        }
    }

    /**
     * @throws TimeOutException
     */
    private function doFlush(): void
    {
        try {
            $this->isFlushing = true;
            $tasks = [];
            $flushBatchSize = $this->options['flushBatchSize'];
            $cachedDocuments = new WeakMap();
            foreach ($this->repositories as $className => $repository) {
                $metadata = $this->classMetadataRegistry->getClassMetadata($className);

                // Compute changed entities
                foreach ($repository->identityMap as $object) {
                    $document = $this->hydrater->hydrateDocumentFromObject($object);
                    $changeset = $this->hydrater->computeChangeset($object, $document);
                    if ([] !== $changeset) {
                        $repository->identityMap->scheduleUpsert($object);
                        $cachedDocuments[$object] = $document; // Avoid normalizing the object twice
                    }
                }

                // Process upserts
                if ($repository->identityMap->nbScheduledUpserts > 0) {
                    $scheduledUpserts = $repository->identityMap->scheduledUpserts;
                    foreach (self::getDocumentsByBatches($scheduledUpserts, $flushBatchSize) as $objects) {
                        $tasks[] = $this->meili->index($metadata->indexUid)->updateDocuments(
                            iterable($objects)
                                ->map(function (object $object) use ($cachedDocuments, $repository) {

                                    if ($repository->identityMap->isScheduledForInsert($object)) {
                                        $event = new PrePersistEvent($object, $repository);
                                        $metadata = $this->classMetadataRegistry->getClassMetadata($object::class);
                                        foreach ($metadata->listeners[PrePersistEvent::class] ?? [] as $listener) {
                                            $listener->invoke($object, [$event]);
                                        }
                                        $this->eventDispatcher->dispatch($event);
                                    } else  {
                                        $event = new PreUpdateEvent($object, $repository);
                                        $metadata = $this->classMetadataRegistry->getClassMetadata($object::class);
                                        foreach ($metadata->listeners[PreUpdateEvent::class] ?? [] as $listener) {
                                            $listener->invoke($object, [$event]);
                                        }
                                        $this->eventDispatcher->dispatch($event);
                                    }

                                    return $cachedDocuments[$object]
                                        ?? $this->hydrater->hydrateDocumentFromObject($object);
                                })
                                ->asArray(),
                        );
                    }
                }

                // Process deletions
                if ($repository->identityMap->nbScheduledDeletions > 0) {
                    $scheduledDeletions = $repository->identityMap->scheduledDeletions;
                    foreach (self::getDocumentsByBatches($scheduledDeletions, $flushBatchSize) as $objects) {
                        $tasks[] = $this->meili->index($metadata->indexUid)->deleteDocuments([
                            'filter' => (string) field($metadata->primaryKey)->isIn(
                                iterable($objects)
                                    ->map(fn (object $object) => $this->hydrater->getIdFromObject($object))
                                    ->asArray(),
                            ),
                        ]);
                    }
                }
            }
            $this->meili->waitForTasks(
                array_column($tasks, 'taskUid'),
                $this->options['flushTimeoutMs'],
                $this->options['flushCheckIntervalMs'],
            );
            // Clear scheduled operations
            foreach ($this->repositories as $repository) {
                $repository->identityMap->scheduledUpserts = [];
                $scheduledDeletions = $repository->identityMap->scheduledDeletions;
                foreach (self::getDocumentsByBatches($scheduledDeletions, $flushBatchSize) as $objects) {
                    foreach ($objects as $object) {
                        $repository->identityMap->forgetState($object);
                        $repository->identityMap->detach($object);
                    }
                }
                $repository->identityMap->scheduledDeletions = [];
            }
            // Update states
            foreach ($cachedDocuments as $object => $document) {
                $this->getRepository($object::class)->identityMap->rememberState($object, $document);
            }
        } finally {
            $this->isFlushing = false;
        }
    }

    public function clear(): void
    {
        foreach ($this->repositories as $repository) {
            $repository->clear();
        }
    }

    /**
     * @return iterable<iterable<object>>
     */
    private static function getDocumentsByBatches(iterable $documents, int $batchSize): iterable
    {
        if (PHP_INT_MAX === $batchSize) {
            return [$documents];
        }

        return iterable_chunk($documents, $batchSize);
    }
}

