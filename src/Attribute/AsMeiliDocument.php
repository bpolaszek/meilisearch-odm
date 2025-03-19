<?php

namespace BenTools\MeilisearchOdm\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMeiliDocument
{
    public function __construct(
        public string $indexUid,
        public string $primaryKey = 'id',
        public array $serializationContext = [],
    ) {
    }
}
