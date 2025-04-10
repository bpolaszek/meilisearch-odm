<?php

namespace BenTools\MeilisearchOdm;

use BenTools\MeilisearchOdm\Misc\UniqueList;
use WeakMap;

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
 * @template T
 * @param iterable<T> $items
 *
 * @return UniqueList<T>
 */
function uniqueList(iterable $items = []): UniqueList
{
    $storage = new UniqueList();
    foreach ($items as $key => $value) {
        $storage[$key] = $value;
    }

    return $storage;
}
