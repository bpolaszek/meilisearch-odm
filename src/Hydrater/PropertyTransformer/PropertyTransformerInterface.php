<?php

namespace BenTools\MeilisearchOdm\Hydrater\PropertyTransformer;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute as AttributeMetadata;

interface PropertyTransformerInterface
{
    public function supports(AttributeMetadata $metadata): bool;

    public function toObjectProperty(mixed $value, AttributeMetadata $metadata): mixed;

    public function toDocumentAttribute(mixed $value, AttributeMetadata $metadata): mixed;
}
