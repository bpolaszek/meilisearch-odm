<?php

namespace BenTools\MeilisearchOdm\Manager;

use BenTools\MeilisearchOdm\Misc\Changeset;
use BenTools\MeilisearchOdm\Misc\UniqueList;
use InvalidArgumentException;
use SplObjectStorage;
use WeakMap;

use function BenTools\IterableFunctions\iterable;
use function BenTools\MeilisearchOdm\uniqueList;
use function BenTools\MeilisearchOdm\weakmap_objects;
use function hash;
use function in_array;
use function serialize;
use function spl_object_hash;

final class UnitOfWork
{
    public const int DELETE = 0;
    public const int CREATE = 1;
    public const int UPDATE = 2;

    private(set) WeakMap $operations;

    /**
     * @var WeakMap<object, Changeset>
     */
    private(set) WeakMap $changesets;
    private(set) string $hash = '';

    /**
     * @var WeakMap<object, UniqueList>
     */
    private(set) WeakMap $firedEvents;

    private SplObjectStorage $scheduledObjects;

    private(set) iterable $removals {
        get => iterable(weakmap_objects($this->operations))->filter(
            fn (object $object) => self::DELETE === $this->operations[$object],
        );
        set => throw new InvalidArgumentException("Read-only value");
    }

    public function __construct(
        private readonly LoadedObjects $loadedObjects,
    ) {
        $this->scheduledObjects = new SplObjectStorage();
        $this->operations = new WeakMap();
        $this->changesets = new WeakMap();
        $this->firedEvents = new WeakMap();
    }

    public function scheduleUpsert(object $object, object ...$objects): void
    {
        $objects = [$object, ...$objects];
        foreach ($objects as $object) {
            $this->scheduledObjects->attach($object);
            $operation = $this->loadedObjects->contains($object) ? self::UPDATE : self::CREATE;
            $this->operations[$object] = $operation;
        }
    }

    public function scheduleDelete(object $object, object ...$objects): void
    {
        $objects = [$object, ...$objects];
        foreach ($objects as $object) {
            $this->scheduledObjects->attach($object);
            $this->operations[$object] = self::DELETE;
        }
    }

    public function computeChangesets(): void
    {
        $this->changesets = new WeakMap();
        $this->hash = '';
        foreach ($this->loadedObjects as $object) {
            $changeset = $this->loadedObjects->computeChangeset($object);
            if ([] !== $changeset->changedProperties) {
                $this->changesets[$object] = $changeset;
                $this->scheduleUpsert($object);
            }
        }

        $upserts = iterable(weakmap_objects($this->operations))
            ->filter(fn (object $object) => in_array($this->operations[$object], [self::CREATE, self::UPDATE], true));

        $removals = iterable(weakmap_objects($this->operations))
            ->filter(fn (object $object) => self::DELETE === $this->operations[$object]);

        foreach ($upserts as $object) {
            $changeset = $this->loadedObjects->computeChangeset($object);
            if ([] !== $changeset->changedProperties) {
                $this->changesets[$object] = $changeset;
                $this->hash = hash('xxh32', $this->hash . serialize($changeset));
            }
        }

        foreach ($removals as $object) {
            $this->hash = hash('xxh32', $this->hash . spl_object_hash($object));
        }
    }

    public function getPendingOperation(object $object)
    {
        return $this->operations[$object];
    }

    public function addFiredEvent(object $object, string $eventClass): void
    {
        $this->firedEvents[$object] ??= uniqueList();
        $this->firedEvents[$object][] = $eventClass;
    }

    public function hasFiredEvent(object $object, string $eventClass): bool
    {
        $this->firedEvents[$object] ??= uniqueList();
        $firedEvents = [...$this->firedEvents[$object]];

        return in_array($eventClass, $firedEvents, true);
    }

    public function __destruct()
    {
        $this->scheduledObjects->removeAll($this->scheduledObjects);
    }
}
