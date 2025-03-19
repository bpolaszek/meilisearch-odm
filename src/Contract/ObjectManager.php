<?php

namespace BenTools\MeilisearchOdm\Contract;

interface ObjectManager
{
    public function contains(object $object): bool;

    public function persist(object $object, object ...$objects): void;

    public function remove(object $object, object ...$objects): void;

    public function flush(): void;

    public function clear(): void;

    public function readPrimaryKey(object $object): string;

}
