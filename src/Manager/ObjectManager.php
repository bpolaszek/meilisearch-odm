<?php

namespace BenTools\MeilisearchOdm\Manager;

use BenTools\MeilisearchOdm\Hydrater\Hydrater;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use BenTools\MeilisearchOdm\Repository\ObjectRepository;
use Meilisearch\Client;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

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
}

