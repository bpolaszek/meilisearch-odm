<?php

namespace BenTools\MeilisearchOdm\Manager;

use BenTools\MeilisearchOdm\Hydrater\Hydrater;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\CoordinatesTransformer;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\DateTimeTransformer;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\PropertyTransformerInterface;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\StringableTransformer;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use BenTools\MeilisearchOdm\Repository\ObjectRepository;
use Meilisearch\Client;
use Meilisearch\Exceptions\TimeOutException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use WeakMap;

use function array_column;
use function BenTools\IterableFunctions\iterable;
use function BenTools\IterableFunctions\iterable_chunk;
use function Bentools\MeilisearchFilters\field;

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

    /**
     * @param PropertyTransformerInterface[] $transformers
     * @param array{flushBatchSize?: int, flushTimeoutMs?: int, flushCheckIntervalMs?: int} $options
     */
    public function __construct(
        public readonly Client $meili = new Client('http://localhost:7700'),
        public readonly ClassMetadataRegistry $classMetadataRegistry = new ClassMetadataRegistry(),
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

    public function persist(object $object): void
    {
        $repository = $this->getRepository($object::class);
        $repository->identityMap->scheduleUpsert($object);
    }

    public function remove(object $object): void
    {
        $repository = $this->getRepository($object::class);
        $repository->identityMap->scheduleDeletion($object);
    }

    /**
     * @throws TimeOutException
     */
    public function flush(): void
    {
        $tasks = [];
        $flushBatchSize = $this->options['flushBatchSize'];
        $objectToDocumentMap = new WeakMap();

        foreach ($this->repositories as $className => $repository) {
            $metadata = $this->classMetadataRegistry->getClassMetadata($className);

            // Compute changed entities
            foreach ($repository->identityMap as $object) {
                $document = $this->hydrater->hydrateDocumentFromObject($object);
                $changeset = $this->hydrater->computeChangeset($object, $document);
                if ([] !== $changeset) {
                    dump('changeset', $changeset);
                    $repository->identityMap->scheduleUpsert($object);
                    $objectToDocumentMap[$object] = $document; // Avoid normalizing the object twice
                }
            }

            // Process upserts
            if ($repository->identityMap->nbScheduledUpserts > 0) {
                $scheduledUpserts = $repository->identityMap->scheduledUpserts;
                foreach (self::getDocumentsByBatches($scheduledUpserts, $flushBatchSize) as $objects) {
                    $tasks[] = $this->meili->index($metadata->indexUid)->updateDocuments(
                        iterable($objects)
                            ->map(function (object $object) use ($objectToDocumentMap) {
                                return $objectToDocumentMap[$object]
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
                        'filter' => field($metadata->primaryKey)->isIn(
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
            $repository->identityMap->scheduledDeletions = [];
            $repository->identityMap->scheduledUpserts = [];
        }

        // Update states
        foreach ($objectToDocumentMap as $object => $document) {
            $this->getRepository($object::class)->identityMap->rememberState($object, $document);
        }
    }

    public function clear(): void
    {
        foreach ($this->repositories as $repository) {
            $repository->clear();
        }
    }

    /**
     * @return list<iterable<object>>
     */
    private static function getDocumentsByBatches(iterable $documents, int $batchSize): array
    {
        if (PHP_INT_MAX === $batchSize) {
            return [$documents];
        }

        return iterable_chunk($documents, $batchSize);
    }
}

