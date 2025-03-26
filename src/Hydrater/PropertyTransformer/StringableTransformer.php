<?php

namespace BenTools\MeilisearchOdm\Hydrater\PropertyTransformer;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute as AttributeMetadata;
use BenTools\MeilisearchOdm\Misc\Reflection\Reflection;

use function ltrim;

class StringableTransformer implements PropertyTransformerInterface
{
    public function supports(AttributeMetadata $metadata): bool
    {
        return $metadata->property->getType() instanceof \ReflectionNamedType
            && !$metadata->property->getType()->isBuiltin()
            && is_a(ltrim($metadata->property->getType()->getName(), '?'), \Stringable::class, true)
            && Reflection::class(ltrim($metadata->property->getType()->getName(), '?'))->hasMethod('fromString')
            && Reflection::method(ltrim($metadata->property->getType()->getName(), '?'), 'fromString')->isStatic()
            && Reflection::method(ltrim($metadata->property->getType()->getName(), '?'), 'fromString')->isPublic()
            ;
    }

    public function toObjectProperty(mixed $value, AttributeMetadata $metadata): mixed
    {
        $targetClassName = ltrim($metadata->property->getType()->getName(), '?');

        return $targetClassName::fromString($value);
    }

    public function toDocumentAttribute(mixed $value, AttributeMetadata $metadata): mixed
    {
        return (string) $value;
    }
}
