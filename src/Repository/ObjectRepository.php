<?php

namespace BenTools\MeilisearchOdm\Repository;

use Bentools\MeilisearchFilters\Expression;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use BenTools\MeilisearchOdm\Event\PostLoadEvent;
use BenTools\MeilisearchOdm\Manager\ObjectManager;
use BenTools\MeilisearchOdm\Misc\LazySearchResult;
use BenTools\MeilisearchOdm\Misc\Reflection\Reflection;
use BenTools\MeilisearchOdm\Misc\Sort\Sort;
use InvalidArgumentException;

use function array_is_list;
use function array_keys;
use function array_map;
use function Bentools\MeilisearchFilters\field;
use function BenTools\MeilisearchOdm\is_stringable;
use function is_array;
use function is_object;
use function is_scalar;

/**
 * @template T
 */
final readonly class ObjectRepository
{
    public function __construct(
        private ObjectManager $objectManager,
        private string $className,
    ) {
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
                'filter' => array_map('strval', $this->resolveFilters($filters)),
                'sort' => array_map('strval', $this->resolveSorts($sort)),
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
        $identityMap = $this->objectManager->loadedObjects;

        return $identityMap->getObject($id, $this->className) ?? (function (string|int $id) {
            $metadata = $this->objectManager->classMetadataRegistry->getClassMetadata($this->className);
            $filter = field($metadata->primaryKey)->equals($id);

            return $this->findOneBy($filter);
        })($id);
    }

    /**
     * @return T
     */
    private function convertDocumentToObject(array $document, ClassMetadata $metadata): object
    {
        $identityMap = $this->objectManager->loadedObjects;
        $id = $this->objectManager->hydrater->getIdFromDocument($document, $metadata);
        if ($identityMap->containsId($id, $this->className)) {
            return $identityMap->getObject($id, $this->className);
        }
        $object = Reflection::class($this->className)->newLazyProxy(function () use ($document) {
            $instance = Reflection::class($this->className)->newInstanceWithoutConstructor();
            $event = new PostLoadEvent($instance, $this->objectManager);
            $metadata = $this->objectManager->classMetadataRegistry->getClassMetadata($this->className);
            foreach ($metadata->listeners[PostLoadEvent::class] ?? [] as $listener) {
                $listener->invoke($instance, [$event]);
            }
            $this->objectManager->eventDispatcher->dispatch($event);

            return $this->objectManager->hydrater->hydrateObjectFromDocument($document, $instance);
        });
        $identityMap->attach($object);
        $identityMap->rememberState($object, $document);

        return $object;
    }

    /**
     * @param Expression|Expression[]|array<string, mixed> $filters
     * @return Expression[]
     */
    private function resolveFilters(Expression|array $filters): array {
        if ($filters instanceof Expression) {
            return [$filters];
        }

        if (!array_is_list($filters)) {
            $expressions = [];
            foreach ($filters as $field => $value) {
                if (is_object($value) && $this->objectManager->classMetadataRegistry->hasClassMetadata($value::class)) {
                    $value = $this->objectManager->hydrater->getIdFromObject($value);
                } elseif (is_object($value) && is_stringable($value)) {
                    $value = (string) $value;
                }
                if (!is_scalar($value) && !is_array($value)) {
                    throw new InvalidArgumentException("Filter value must be scalar or array");
                }
                $expressions[] = field($field)->isIn((array) $value);
            }

            return $expressions;
        }

        return (fn (Expression ...$expressions) => $expressions)(...$filters);
    }

    /**
     * @param Sort|Sort[]|array<string, 'asc' | 'desc'> $sorts
     * @return Sort[]
     */
    private function resolveSorts(Sort|array $sorts): array
    {
        if ($sorts instanceof Sort) {
            return [$sorts];
        }

        if (!array_is_list($sorts)) {
            return array_map(fn (string $field, string $direction) => new Sort($field, $direction), array_keys($sorts), $sorts);
        }

        return (fn (Sort ...$sorts) => $sorts)(...$sorts);
    }
}
