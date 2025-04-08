<?php

namespace BenTools\MeilisearchOdm\Event;

use BenTools\MeilisearchOdm\Repository\ObjectRepository;

/**
 * @template T
 */
final readonly class PostLoadEvent
{
    /**
     * @param T $object
     */
    public function __construct(
        public object $object,
        public ObjectRepository $repository,
    ) {
    }
}
