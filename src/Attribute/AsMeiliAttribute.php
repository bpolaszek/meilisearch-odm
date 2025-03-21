<?php

namespace BenTools\MeilisearchOdm\Attribute;

use Attribute;
use ReflectionProperty;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class AsMeiliAttribute
{
    public AsMeiliDocument $classMetadata;
    public ReflectionProperty $property;

    public function __construct(
        public readonly ?string $attributeName = null,
        public readonly ?MeiliRelation $relation = null,
    ) {
    }
}
