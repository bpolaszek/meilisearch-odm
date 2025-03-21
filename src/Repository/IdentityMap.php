<?php

namespace BenTools\MeilisearchOdm\Repository;

use WeakMap;

use function BenTools\IterableFunctions\iterable;
use function BenTools\MeilisearchOdm\weakmap_objects;

final class IdentityMap
{
    private const int DELETE = 0;
    private const int UPSERT = 1;
    private array $storage = [];
    private WeakMap $operations;

    public iterable $scheduledUpserts {
        get => iterable(weakmap_objects($this->operations))->filter(
            fn ($object) => self::UPSERT === $this->operations[$object],
        );
        set {
            foreach ($this->operations as $object => $operation) {
                if (self::UPSERT === $operation) {
                    unset($this->operations[$object]);
                }
            }
        }
    }

    public iterable $scheduledDeletions {
        get => iterable(weakmap_objects($this->operations))->filter(
            fn ($object) => self::DELETE === $this->operations[$object],
        );
        set {
            foreach ($this->operations as $object => $operation) {
                if (self::DELETE === $operation) {
                    unset($this->operations[$object]);
                }
            }
        }
    }

    public function __construct()
    {
        $this->operations = new WeakMap();
    }

    public function isScheduledForUpsert(object $object): bool
    {
        return isset($this->operations[$object]) && self::UPSERT === $this->operations[$object];
    }

    public function isScheduledForDeletion(object $object): bool
    {
        return isset($this->operations[$object]) && self::DELETE === $this->operations[$object];
    }

    public function scheduleUpsert(object $object): void
    {
        if ($this->isScheduledForDeletion($object)) {
            return;
        }

        $this->operations[$object] = self::UPSERT;
    }

    public function scheduleDeletion(object $object): void
    {
        $this->operations[$object] = self::DELETE;
    }

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

    public function clear(): void
    {
        $this->storage = [];
        $this->operations = new WeakMap();
    }
}
