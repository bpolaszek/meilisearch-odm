<?php

namespace BenTools\MeilisearchOdm;

use Bentools\MeilisearchFilters\Expression;
use BenTools\MeilisearchOdm\Misc\Sort\Sort;
use WeakMap;

use function array_is_list;
use function array_map;
use function Bentools\MeilisearchFilters\field;
use function Bentools\MeilisearchFilters\filterBuilder;
use function in_array;

/**
 * @internal
 */
function weakmap_objects(WeakMap $weakmap): iterable
{
    foreach ($weakmap as $key => $value) {
        yield $key;
    }
}

/**
 * @internal
 */
function weakmap_values(WeakMap $weakmap): array
{
    $values = [];
    foreach ($weakmap as $value) {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    return $values;
}

/**
 * @param Expression|Expression[]|array<string, mixed> $filters
 * @return Expression[]
 */
function resolve_filters(Expression|array $filters): array {
    if ($filters instanceof Expression) {
        return [$filters];
    }

    if (!array_is_list($filters)) {
        $expressions = [];
        foreach ($filters as $field => $value) {
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
function resolve_sorts(Sort|array $sorts): array
{
    if ($sorts instanceof Sort) {
        return [$sorts];
    }

    if (!array_is_list($sorts)) {
        return array_map(fn (string $field, string $direction) => new Sort($field, $direction), array_keys($sorts), $sorts);
    }

    return (fn (Sort ...$sorts) => $sorts)(...$sorts);
}
