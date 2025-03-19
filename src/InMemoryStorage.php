<?php

namespace BenTools\MeilisearchOdm;

/**
 * @internal
 */
final class InMemoryStorage
{
    private array $storage = [];

    public function store(mixed $documentId, object $document): void
    {
        $this->storage[$documentId] = $document;
    }

    public function get(mixed $documentId): ?object
    {
        return $this->storage[$documentId] ?? null;
    }

    public function has(mixed $documentId): bool
    {
        return isset($this->storage[$documentId]);
    }

    public function clear(): void
    {
        $this->storage = [];
    }
}
