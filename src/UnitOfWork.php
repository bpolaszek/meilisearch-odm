<?php

namespace BenTools\MeilisearchOdm;

use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use SplObjectStorage;
use WeakMap;

use function BenTools\IterableFunctions\iterable_filter;

final class UnitOfWork
{
    private SplObjectStorage $scheduledForAddOrUpdate;
    private SplObjectStorage $scheduledForAddOrReplace;
    private SplObjectStorage $scheduledForDelete;

    /**
     * @var WeakMap<object, string>
     */
    private WeakMap $indexes;

    public function __construct()
    {
        $this->scheduledForAddOrUpdate = new SplObjectStorage();
        $this->scheduledForAddOrReplace = new SplObjectStorage();
        $this->scheduledForDelete = new SplObjectStorage();
        $this->indexes = new WeakMap();
    }

    public function scheduleAddOrUpdate(object $object, ClassMetadata $metadata): void
    {
        $this->scheduledForAddOrUpdate->attach($object);
        $this->indexes[$object] = $metadata->indexUid;
    }

    public function scheduleAddOrReplace(object $object, ClassMetadata $metadata): void
    {
        $this->scheduledForAddOrReplace->attach($object);
        $this->indexes[$object] = $metadata->indexUid;
    }

    public function scheduleDelete(object $object, ClassMetadata $metadata): void
    {
        $this->scheduledForDelete->attach($object);
        $this->indexes[$object] = $metadata->indexUid;
    }

    public function contains(object $object): bool
    {
        return $this->scheduledForAddOrUpdate->contains($object)
            || $this->scheduledForAddOrReplace->contains($object)
            || $this->scheduledForDelete->contains($object);
    }

    public function getIndexUids(): array
    {
        return weakmap_values($this->indexes);
    }

    public function getScheduledForAddOrUpdate(string $index): iterable
    {
        return iterable_filter(
            $this->scheduledForAddOrUpdate,
            fn (object $object) => $this->indexes[$object] === $index,
        );
    }

    public function getScheduledForAddOrReplace(string $index): iterable
    {
        return iterable_filter(
            $this->scheduledForAddOrReplace,
            fn (object $object) => $this->indexes[$object] === $index,
        );
    }

    public function getScheduledForDelete(string $index): iterable
    {
        return iterable_filter(
            $this->scheduledForDelete,
            fn (object $object) => $this->indexes[$object] === $index,
        );
    }
}
