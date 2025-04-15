<?php

namespace BenTools\MeilisearchOdm\Hydrater\PropertyTransformer;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute as AttributeMetadata;
use BenTools\MeilisearchOdm\Attribute\MeiliRelationType;
use BenTools\MeilisearchOdm\Manager\ObjectManager;

final readonly class ManyToOneRelationTransformer implements PropertyTransformerInterface
{
    public function __construct(
        private ObjectManager $objectManager,
    ) {
    }

    public function supports(AttributeMetadata $metadata): bool
    {
        return $metadata->relation?->type === MeiliRelationType::MANY_TO_ONE;
    }

    public function toObjectProperty(mixed $value, AttributeMetadata $metadata): ?object
    {
        $targetClass = $metadata->relation->targetClass;
        $repository = $this->objectManager->getRepository($targetClass);
        $attributeName = $metadata->relation->targetAttribute
            ?? $this->objectManager->getClassMetadata($targetClass)->primaryKey;

        return $repository->findOneBy([$attributeName => $value]);
    }

    public function toDocumentAttribute(mixed $value, AttributeMetadata $metadata): mixed
    {
        $propertyPath = $metadata->relation->targetAttribute
            ?? $this->objectManager->getClassMetadata($metadata->relation->targetClass)->idProperty;

        return $this->objectManager->propertyAccessor->getValue($value, $propertyPath);
    }
}
