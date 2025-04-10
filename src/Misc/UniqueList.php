<?php

namespace BenTools\MeilisearchOdm\Misc;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Traversable;

use function array_key_exists;
use function count;
use function in_array;

/**
 * @template T
 * @implements ArrayAccess<int, T>
 * @implements IteratorAggregate<T>
 */
final class UniqueList implements ArrayAccess, IteratorAggregate, Countable
{
    private array $storage = [];

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->storage);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->storage[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (in_array($value, $this->storage, true)) {
            return;
        }

        $this->storage[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->storage[$offset]);
    }

    public function getIterator(): Traversable
    {
        yield from $this->storage;
    }

    public function count(): int
    {
        return count($this->storage);
    }
}
