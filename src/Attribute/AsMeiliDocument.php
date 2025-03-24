<?php

namespace BenTools\MeilisearchOdm\Attribute;

use Attribute;
use ReflectionProperty;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsMeiliDocument
{
    /**
     * @var AsMeiliAttribute[]
     */
    private(set) array $properties = [];
    private(set) string $idProperty;

    public function __construct(
        public string $indexUid,
        public string $primaryKey = 'id',
    ) {
    }

    public function registerProperty(ReflectionProperty $reflProperty, AsMeiliAttribute $attribute): void
    {
        $propertyName = $reflProperty->getName();
        $this->properties[$propertyName] = $attribute;
        $attribute->classMetadata = $this;
        $attribute->property = $reflProperty;
        $attributeName = $attribute->attributeName ?? $propertyName;
        if ($attributeName === $this->primaryKey) {
            $this->idProperty = $propertyName;
        }
    }
}
