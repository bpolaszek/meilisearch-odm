<?php

namespace BenTools\MeilisearchOdm;

use Bentools\MeilisearchFilters\Expression;
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
        $filter = filterBuilder();
        foreach ($filters as $field => $value) {
            $filter = $filter->and(field($field)->isIn((array) $value));
        }

        return [$filter];
    }

    return (fn (Expression ...$expressions) => $expressions)(...$filters);
}
