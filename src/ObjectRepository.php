<?php

namespace BenTools\MeilisearchOdm;

use AutoMapper\AutoMapperInterface;
use Bentools\MeilisearchFilters\Expression;
use BenTools\MeilisearchOdm\Contract\ObjectRepository as RepositoryInterface;
use BenTools\MeilisearchOdm\Contract\ObjectManager as ObjectManagerInterface;

use InvalidArgumentException;

use RuntimeException;

use function array_map;
use function BenTools\IterableFunctions\iterable;
use function is_a;

class ObjectRepository implements RepositoryInterface
{
    private InMemoryStorage $storage;

    public function __construct(
        private readonly string $className,
        public readonly ObjectManagerInterface $manager,
        public readonly AutoMapperInterface $mapper,
    ) {
        $this->storage = new InMemoryStorage();
    }

    public function clear(): void
    {
        $this->storage->clear();
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function contains(object $object): bool
    {
        return $this->storage->has($this->manager->readPrimaryKey($object));
    }

    public function findBy(
        Expression|array $filters = [],
        array $sort = [],
        int $limit = PHP_INT_MAX,
        int $offset = 0,
    ): iterable {
        $filters = resolve_filters($filters);
        $metadata = $this->manager->readClassMetadata($this->getClassName());
        $index = $this->manager->meili->index($metadata->indexUid);
        $params = [
            'filter' => array_map('strval', $filters),
            'limit' => $limit,
            'offset' => $offset,
            'sort' => $sort,
        ];
        $documents = $index->search('', $params);

        return iterable($documents)->map(function (array $document) use ($metadata) {
            $primaryKey = $metadata->primaryKey;
            if (!isset($document[$primaryKey])) {
                throw new RuntimeException(
                    sprintf('Document does not have a primary key "%s"', $primaryKey),
                );
            }
            $existingObject = $this->storage->get($document[$metadata->primaryKey]);

            $object = $this->hydrate($document, $existingObject);
            $this->storage->store($document[$primaryKey], $object);

            return $object;
        });
    }

    public function findOneBy(Expression|array $filters, array $sort = []): ?object
    {
        foreach ($this->findBy($filters, $sort, 1) as $object) {
            return $object;
        }

        return null;
    }

    public function find(mixed $documentId): ?object
    {
        $primaryKey = $this->manager->readClassMetadata($this->getClassName())->primaryKey;

        return $this->findOneBy([$primaryKey => $documentId]);
    }

    public function hydrate(array $document, ?object $object = null): object
    {
        if (null !== $object && !is_a($object, $this->getClassName())) {
            throw new InvalidArgumentException(
                sprintf('Expected an instance of %s, got %s', $this->getClassName(), get_class($object)),
            );
        }

        return $this->mapper->map($document, $object ?? $this->getClassName());
    }
}
