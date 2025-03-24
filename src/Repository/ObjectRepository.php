<?php

namespace BenTools\MeilisearchOdm\Repository;

use Bentools\MeilisearchFilters\Expression;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use BenTools\MeilisearchOdm\Manager\ObjectManager;
use BenTools\MeilisearchOdm\Misc\LazySearchResult;
use BenTools\MeilisearchOdm\Misc\Reflection\Reflection;
use BenTools\MeilisearchOdm\Misc\Sort\Sort;

use function array_map;
use function Bentools\MeilisearchFilters\field;
use function BenTools\MeilisearchOdm\resolve_filters;
use function BenTools\MeilisearchOdm\resolve_sorts;

use const PHP_INT_MAX;

/**
 * @template T
 */
final readonly class ObjectRepository
{
    public IdentityMap $identityMap;

    public function __construct(
        private ObjectManager $objectManager,
        private string $className,
    ) {
        $this->identityMap = new IdentityMap();
    }

    /**
     * @return iterable<T>
     */
    public function findBy(
        Expression|array $filters = [],
        Sort|array $sort = [],
        $limit = PHP_INT_MAX,
        $offset = 0,
        array $params = [],
    ): LazySearchResult {
        $metadata = $this->objectManager->classMetadataRegistry->getClassMetadata($this->className);

        return new LazySearchResult(
            $this->objectManager->meili,
            $metadata->indexUid,
            '',
            [
                'filter' => array_map('strval', resolve_filters($filters)),
                'sort' => array_map('strval', resolve_sorts($sort)),
                'limit' => $limit,
                'offset' => $offset,
                ...$params,
            ],
            fn (array $document) => $this->convertDocumentToObject($document, $metadata),
        );
    }

    /**
     * @return T|null
     */
    public function findOneBy(
        Expression|array $filters = [],
        Sort|array $sort = [],
    ): ?object {
        $hits = $this->findBy($filters, $sort, limit: 1);

        return [...$hits][0] ?? null;
    }

    /**
     * @return T|null
     */
    public function find(string|int $id): ?object
    {
        if ($this->identityMap->contains($id)) {
            return $this->identityMap->get($id);
        }

        $metadata = $this->objectManager->classMetadataRegistry->getClassMetadata($this->className);
        $filter = field($metadata->primaryKey)->equals($id);

        return $this->findOneBy($filter);
    }

    public function clear(): void
    {
        $this->identityMap->clear();
    }

    /**
     * @return T
     */
    private function convertDocumentToObject(array $document, ClassMetadata $metadata): object
    {
        $id = $this->objectManager->hydrater->getIdFromDocument($document, $metadata);
        if ($this->identityMap->contains($id)) {
            return $this->identityMap->get($id);
        }
        $object = Reflection::class($this->className)->newLazyProxy(function () use ($document) {
            $object = new ($this->className)();

            return $this->objectManager->hydrater->hydrateObjectFromDocument($document, $object);
        });
        $this->identityMap->attach($id, $object);

        return $object;
    }
}
