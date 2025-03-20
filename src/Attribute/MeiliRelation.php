<?php

namespace BenTools\MeilisearchOdm\Attribute;

final readonly class MeiliRelation
{
    public function __construct(
        public string $targetClass,
        public MeiliRelationType $type,
        public ?string $targetAttributeName = null,
    ) {
    }
}
