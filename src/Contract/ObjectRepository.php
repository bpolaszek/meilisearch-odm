<?php

namespace BenTools\MeilisearchOdm\Contract;

use Bentools\MeilisearchFilters\Expression;

use const PHP_INT_MAX;

/**
 * @template T
 */
interface ObjectRepository
{

    /**
     * @return class-string<T>
     */
    public function getClassName(): string;

    /**
     * @return iterable<T>
     */
    public function findBy(
        Expression|array $filters = [],
        array $sort = [],
        int $limit = PHP_INT_MAX,
        int $offset = 0,
    ): iterable;

    /**
     * @return T|null
     */
    public function findOneBy(Expression|array $filters, array $sort = []): ?object;

    /**
     * @return T|null
     */
    public function find(mixed $documentId): ?object;

    /**
     * @param T|null $object
     * @return T
     */
    public function hydrate(array $document, ?object $object = null): object;

    public function contains(object $object): bool;

    public function clear(): void;
}
