<?php

namespace BenTools\MeilisearchOdm\Repository;

final class IdentityMap
{
    private array $storage = [];

    public function contains(string|int $id): bool
    {
        return isset($this->storage[$id]);
    }

    public function get(string|int $id): ?object
    {
        return $this->storage[$id] ?? null;
    }

    public function store(string|int $id, object $object): void
    {
        $this->storage[$id] = $object;
    }
}
