<?php

namespace BenTools\MeilisearchOdm\Manager;

use BenTools\MeilisearchOdm\Hydrater\Hydrater;
use BenTools\MeilisearchOdm\Misc\Changeset;
use Exception;
use SplObjectStorage;
use Traversable;
use WeakMap;

final class LoadedObjects implements \IteratorAggregate
{
    private SplObjectStorage $storage;
    private WeakMap $ids;
    private WeakMap $rememberedStates;

    public function __construct(
        private readonly Hydrater $hydrater,
    )
    {
        $this->storage = new SplObjectStorage();
        $this->ids = new WeakMap();
        $this->rememberedStates = new WeakMap();
    }

    public function attach(object $object): void
    {
        $id = $this->hydrater->getIdFromObject($object);
        $this->storage->attach($object);
        $this->ids[$object] = $id;
    }

    public function rememberState(object $object, array $document): void
    {
        $this->rememberedStates[$object] = $document;
    }

    public function forgetState(object $object): void
    {
        unset($this->rememberedStates[$object]);
    }

    public function detach(object $object): void
    {
        if ($this->storage->contains($object)) {
            $this->storage->detach($object);
            unset($this->ids[$object]);
        }
    }

    public function contains(object $object): bool
    {
        return $this->storage->contains($object);
    }

    public function containsId(string|int $id, string $className): bool
    {
        return null !== $this->getObject($id, $className);
    }

    public function getObject(string|int $id, string $className): ?object
    {
        foreach ($this->ids as $object => $objectId) {
            if (($object::class === $className) && 0 == ($objectId <=> $id)) {
                return $object;
            }
        }

        return null;
    }

    public function computeChangeset(object $object, ?array $document = null): Changeset
    {
        $document ??= $this->hydrater->hydrateDocumentFromObject($object);
        $rememberedState = $this->rememberedStates[$object] ?? [];

        return new Changeset($document, $rememberedState);
    }

    public function getIterator(): Traversable
    {
        yield from $this->storage;
    }
}
