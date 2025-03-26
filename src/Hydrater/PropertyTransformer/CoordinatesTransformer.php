<?php

namespace BenTools\MeilisearchOdm\Hydrater\PropertyTransformer;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute as AttributeMetadata;
use BenTools\MeilisearchOdm\Misc\Geo\Coordinates;
use BenTools\MeilisearchOdm\Misc\Geo\CoordinatesInterface;
use BenTools\MeilisearchOdm\Misc\Reflection\Reflection;
use ReflectionNamedType;

use function ltrim;

class CoordinatesTransformer implements PropertyTransformerInterface
{
    public function supports(AttributeMetadata $metadata): bool
    {
        return $metadata->property->getSettableType() instanceof ReflectionNamedType
            && Reflection::isTypeCompatible($metadata->property->getSettableType(), CoordinatesInterface::class);
    }

    public function toObjectProperty(mixed $value, AttributeMetadata $metadata): ?CoordinatesInterface
    {
        if (null === $value) {
            return null;
        }

        $type = $metadata->property->getSettableType();
        if (!$type instanceof ReflectionNamedType) {
            throw new \LogicException("Property type is not a named type.");
        }

        $targetClass = ltrim($type->getName(), '?');
        if (!Reflection::class($targetClass)->isInstantiable()) {
            return new Coordinates($value['lat'], $value['lng']);
        }

        /** @var CoordinatesInterface $coordinates */
        $coordinates = Reflection::class($targetClass)->newInstanceWithoutConstructor();
        $coordinates->latitude = $value['lat'];
        $coordinates->longitude = $value['lng'];

        return $coordinates;
    }

    /**
     * @param CoordinatesInterface $value
     */
    public function toDocumentAttribute(mixed $value, AttributeMetadata $metadata): ?array
    {
        if (null === $value) {
            return null;
        }

        return [
            'lat' => $value->latitude,
            'lng' => $value->longitude,
        ];
    }

}
