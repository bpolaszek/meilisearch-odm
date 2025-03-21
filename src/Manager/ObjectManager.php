<?php

namespace BenTools\MeilisearchOdm\Manager;

use BenTools\MeilisearchOdm\Hydrater\Hydrater;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use BenTools\MeilisearchOdm\Repository\ObjectRepository;
use Meilisearch\Client;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function array_column;
use function BenTools\IterableFunctions\iterable;
use function BenTools\IterableFunctions\iterable_chunk;
use function Bentools\MeilisearchFilters\field;

final class ObjectManager
{
    private const array DEFAULT_OPTIONS = [
        'flushBatchSize' => PHP_INT_MAX,
        'flushTimeoutMs' => 5000,
        'flushCheckIntervalMs' => 50,
    ];

    public Hydrater $hydrater;

    /**
     * @var array<class-string, ObjectRepository>
     */
    private array $repositories;

    /**
     * @var array{flushBatchSize: int, flushTimeoutMs: int, flushCheckIntervalMs: int}
     */
    private array $options;

    public function __construct(
        public readonly Client $meili = new Client('http://localhost:7700'),
        public readonly ClassMetadataRegistry $classMetadataRegistry = new ClassMetadataRegistry(),
        PropertyAccessorInterface $propertyAccessor = new PropertyAccessor(),
        array $options = [],
    ) {
        $this->hydrater = new Hydrater($this, $propertyAccessor);
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
        })($className);
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

    public function flush(): void
    {
        $tasks = [];
        foreach ($this->repositories as $className => $repository) {
            $metadata = $this->classMetadataRegistry->getClassMetadata($className);
            if ($repository->identityMap->nbScheduledUpserts > 0) {
                $scheduledUpserts = $repository->identityMap->scheduledUpserts;
                foreach (self::getDocumentsByBatches($scheduledUpserts, $this->options['flushBatchSize']) as $objects) {
                    $tasks[] = $this->meili->index($metadata->indexUid)->updateDocuments(iterable($objects)->asArray());
                }
            }
            if ($repository->identityMap->nbScheduledDeletions > 0) {
                $scheduledDeletions = $repository->identityMap->scheduledDeletions;
                foreach (self::getDocumentsByBatches($scheduledDeletions, $this->options['flushCheckIntervalMs']) as $objects) {
                    $tasks[] = $this->meili->index($metadata->indexUid)->deleteDocuments([
                        'filter' => field($metadata->primaryKey)->isIn(
                            iterable($objects)
                                ->map(fn (object $object) => $this->hydrater->getIdFromObject($object, $metadata))
                                ->asArray()
                        )
                    ]);
                }
            }
        }

        $this->meili->waitForTasks(
            array_column($tasks, 'taskUid'),
            $this->options['flushTimeoutMs'],
            $this->options['flushCheckIntervalMs'],
        );

        foreach ($this->repositories as $repository) {
            $repository->identityMap->scheduledDeletions = [];
            $repository->identityMap->scheduledUpserts = [];
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

