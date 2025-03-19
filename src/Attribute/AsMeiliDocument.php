<?php

namespace BenTools\MeilisearchOdm\Attribute;

use Attribute;

/**
 * @codeCoverageIgnore 
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMeiliDocument
{
    public function __construct(
        public string $indexUid,
        public string $primaryKey = 'id',
    ) {
    }
}
