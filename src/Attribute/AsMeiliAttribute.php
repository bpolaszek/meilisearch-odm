<?php

namespace BenTools\MeilisearchOdm\Attribute;

use Attribute;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\PropertyTransformerInterface;
use ReflectionProperty;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class AsMeiliAttribute
{
    public AsMeiliDocument $classMetadata;
    public ReflectionProperty $property;

    public function __construct(
        public readonly ?string $attributeName = null,
        public readonly ?MeiliRelation $relation = null,
        public readonly ?PropertyTransformerInterface $transformer = null,
        public readonly ?bool $filterable = null,
    ) {
    }
}
