<?php

namespace BenTools\MeilisearchOdm;

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
