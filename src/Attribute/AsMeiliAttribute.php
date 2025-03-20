<?php

namespace BenTools\MeilisearchOdm\Attribute;

use Attribute;

/**
 * @codeCoverageIgnore
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class AsMeiliAttribute
{
    public function __construct(
        public ?string $attributeName = null,
        public ?MeiliRelation $relation = null,
    ) {
    }
}
