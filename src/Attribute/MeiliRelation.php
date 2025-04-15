<?php

namespace BenTools\MeilisearchOdm\Attribute;

final readonly class MeiliRelation
{
    public function __construct(
        public MeiliRelationType $type,
        public string $targetClass,
        public ?string $targetAttribute = null,
    ) {
    }
}
