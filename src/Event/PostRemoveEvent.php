<?php

namespace BenTools\MeilisearchOdm\Event;

use BenTools\MeilisearchOdm\Manager\ObjectManager;

/**
 * @template T
 */
final readonly class PostRemoveEvent
{
    /**
     * @param T $object
     */
    public function __construct(
        public object $object,
        public ObjectManager $objectManager,
    ) {
    }
}
