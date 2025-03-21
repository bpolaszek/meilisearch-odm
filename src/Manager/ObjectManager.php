<?php

namespace BenTools\MeilisearchOdm\Manager;

use BenTools\MeilisearchOdm\Hydrater\Hydrater;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use BenTools\MeilisearchOdm\Repository\ObjectRepository;
use Meilisearch\Client;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function BenTools\IterableFunctions\iterable;
use function Bentools\MeilisearchFilters\field;

final class ObjectManager
{
    public Hydrater $hydrater;

    /**
     * @var array<class-string, ObjectRepository>
     */
    private array $repositories;

    public function __construct(
        public readonly Client $meili = new Client('http://localhost:7700'),
        public readonly ClassMetadataRegistry $classMetadataRegistry = new ClassMetadataRegistry(),
        PropertyAccessorInterface $propertyAccessor = new PropertyAccessor(),
    ) {
        $this->hydrater = new Hydrater($this, $propertyAccessor);
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
        foreach ($this->repositories as $className => $repository) {
            $metadata = $this->classMetadataRegistry->getClassMetadata($className);
            $scheduledUpserts = $repository->identityMap->scheduledUpserts;
            $this->meili->index($metadata->indexUid)->updateDocuments(iterable($scheduledUpserts)->asArray());
            $scheduledDeletions = $repository->identityMap->scheduledDeletions;
            $this->meili->index($metadata->indexUid)->deleteDocuments([
                'filter' => field($metadata->primaryKey)->isIn(
                    iterable($scheduledDeletions)
                        ->map(fn (object $object) => $this->hydrater->getIdFromObject($object, $metadata))
                        ->asArray()
                )
            ]);
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
}

