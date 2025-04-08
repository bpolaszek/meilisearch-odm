<?php

namespace BenTools\MeilisearchOdm\Repository;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;
use WeakMap;

use function BenTools\IterableFunctions\iterable;
use function BenTools\MeilisearchOdm\weakmap_objects;
use function count;

/**
 * @template T
 * @implements IteratorAggregate<T>
 */
final class IdentityMap implements IteratorAggregate, Countable
{
    private const int DELETE = 0;
    private const int UPSERT = 1;

    /**
     * @var array<string|int, object>
     */
    private array $storage = [];
    private WeakMap $operations;
    private WeakMap $ids;
    private(set) Weakmap $rememberedStates;
    private WeakMap $pendingInserts;

    public iterable $scheduledUpserts {
        get => iterable(weakmap_objects($this->operations))->filter(
            fn ($object) => self::UPSERT === $this->operations[$object],
        );
        set {
            if ([] !== $value) {
                throw new InvalidArgumentException("Invalid value");
            }
            foreach ($this->operations as $object => $operation) {
                if (self::UPSERT === $operation) {
                    unset($this->operations[$object]);
                }
            }
            $this->pendingInserts = new WeakMap();
            $this->nbScheduledUpserts = 0;
        }
    }

    public iterable $scheduledDeletions {
        get => iterable(weakmap_objects($this->operations))->filter(
            fn ($object) => self::DELETE === $this->operations[$object],
        );
        set {
            if ([] !== $value) {
                throw new InvalidArgumentException("Invalid value");
            }
            foreach ($this->operations as $object => $operation) {
                if (self::DELETE === $operation) {
                    unset($this->operations[$object]);
                }
            }
            $this->nbScheduledDeletions = 0;
        }
    }

    private(set) int $nbScheduledUpserts = 0;
    private(set) int $nbScheduledDeletions = 0;

    public function __construct()
    {
        $this->operations = new WeakMap();
        $this->rememberedStates = new WeakMap();
        $this->ids = new WeakMap();
        $this->pendingInserts = new WeakMap();
    }

    public function isScheduledForInsert(object $object): bool
    {
        return isset($this->pendingInserts[$object]);
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
        if ($this->isScheduledForDeletion($object) || $this->isScheduledForUpsert($object)) {
            return;
        }

        if (!isset($this->rememberedStates[$object])) {
            $this->pendingInserts[$object] = true;
        }

        $this->operations[$object] = self::UPSERT;
        $this->nbScheduledUpserts++;
    }

    public function scheduleDeletion(object $object): void
    {
        if ($this->isScheduledForDeletion($object)) {
            return; // @codeCoverageIgnore
        }

        if ($this->isScheduledForUpsert($object)) {
            $this->nbScheduledUpserts--;
        }

        $this->operations[$object] = self::DELETE;
        $this->nbScheduledDeletions++;
    }

    public function contains(string|int $id): bool
    {
        return isset($this->storage[$id]);
    }

    public function get(string|int $id): ?object
    {
        return $this->storage[$id] ?? null;
    }

    public function attach(string|int $id, object $object): void
    {
        $this->storage[$id] = $object;
        $this->ids[$object] = $id;
    }

    public function detach(object $object): void
    {
        $id = $this->ids[$object] ?? null;
        if (null !== $id) {
            unset($this->storage[$id]);
            unset($this->ids[$object]);
        }
    }

    public function rememberState(object $object, array $document): void
    {
        $this->rememberedStates[$object] = $document;
    }

    public function forgetState(object $object): void
    {
        unset($this->rememberedStates[$object]);
    }

    public function clear(): void
    {
        $this->storage = [];
        $this->operations = new WeakMap();
        $this->rememberedStates = new WeakMap();
        $this->pendingInserts = new WeakMap();
        $this->ids = new WeakMap();
        $this->nbScheduledUpserts = 0;
        $this->nbScheduledDeletions = 0;
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
