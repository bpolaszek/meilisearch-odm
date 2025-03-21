<?php

namespace BenTools\MeilisearchOdm\Hydrater\PropertyTransformer;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute as AttributeMetadata;
use DateTimeImmutable;
use InvalidArgumentException;

use function sprintf;

final readonly class DateTimeTransformer implements PropertyTransformerInterface
{
    public function toObjectProperty(mixed $value, AttributeMetadata $metadata): ?DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        return DateTimeImmutable::createFromTimestamp((int) $value);
    }

    public function toDocumentAttribute(mixed $value, AttributeMetadata $metadata): ?int
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->getTimestamp();
        }

        throw new InvalidArgumentException(sprintf("Expected a DateTimeImmutable instance, got %s", get_debug_type($value)));
    }
}
