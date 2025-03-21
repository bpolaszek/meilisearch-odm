<?php

namespace BenTools\MeilisearchOdm\Repository;

use Bentools\MeilisearchFilters\Expression;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use BenTools\MeilisearchOdm\Manager\ObjectManager;
use BenTools\MeilisearchOdm\Misc\Sort\Sort;
use Meilisearch\Search\SearchResult;

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
     * @return SearchResult|iterable<T>
     */
    public function findBy(
        Expression|array $filters = [],
        Sort|array $sort = [],
        $limit = PHP_INT_MAX,
        $offset = 0,
        array $params = [],
    ): SearchResult {
        $metadata = $this->objectManager->classMetadataRegistry->getClassMetadata($this->className);
        $index = $this->objectManager->meili->index($metadata->indexUid);
        $searchParams = [
            'filter' => array_map('strval', resolve_filters($filters)),
            'sort' => array_map('strval', resolve_sorts($sort)),
            'limit' => $limit,
            'offset' => $offset,
            ...$params,
        ];
        $searchResult = $index->search('', $searchParams);

        return $searchResult->transformHits(fn (array $hits) => $this->transformHits($hits, $metadata));
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
        $hits = $this->findBy($filter, limit: 1);

        return [...$hits][0] ?? null;
    }

    public function clear(): void
    {
        $this->identityMap->clear();
    }

    private function transformHits(array $hits, ClassMetadata $metadata): array
    {
        return array_map(
            function ($document) use ($metadata) {
                $id = $this->objectManager->hydrater->getIdFromDocument($document, $metadata);
                if ($this->identityMap->contains($id)) {
                    return $this->identityMap->get($id);
                }
                $object = $this->objectManager->hydrater->hydrate($document, new $this->className(), $metadata);
                $this->identityMap->store($id, $object);

                return $object;
            },
            $hits,
        );
    }
}
