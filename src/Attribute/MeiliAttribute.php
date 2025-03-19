<?php

namespace BenTools\MeilisearchOdm\Attribute;

use Attribute;

/**
 * @codeCoverageIgnore
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class MeiliAttribute
{
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
