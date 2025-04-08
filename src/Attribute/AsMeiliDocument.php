<?php

namespace BenTools\MeilisearchOdm\Attribute;

use Attribute;
use ReflectionMethod;
use ReflectionProperty;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsMeiliDocument
{
    /**
     * @var array<string, AsMeiliAttribute>
     */
    private(set) array $properties = [];
    private(set) string $idProperty;

    /**
     * @var array<string, ReflectionMethod[]>
     */
    private(set) array $listeners = [];

    public function __construct(
        public string $indexUid,
        public string $primaryKey = 'id',
    ) {
    }

    /**
     * @internal
     */
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

    /**
     * @internal
     */
    public function registerListener(string $eventName, ReflectionMethod $reflMethod): void
    {
        $this->listeners[$eventName][] = $reflMethod;
    }
}
