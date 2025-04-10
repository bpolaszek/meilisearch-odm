<?php

namespace BenTools\MeilisearchOdm\Hydrater;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute as AttributeMetadata;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\PropertyTransformerInterface;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function array_map;
use function explode;
use function implode;
use function sprintf;

final readonly class Hydrater
{
    /**
     * @param PropertyTransformerInterface[] $transformers
     */
    public function __construct(
        private ClassMetadataRegistry $classMetadataRegistry,
        private PropertyAccessorInterface $propertyAccessor = new PropertyAccessor(),
        private array $transformers = [],
    ) {
    }

    public function hydrateObjectFromDocument(array $document, object $object): object
    {
        $metadata = $this->classMetadataRegistry->getClassMetadata($object::class);
        foreach ($metadata->properties as $propertyName => $meiliAttribute) {
            $attributeName = $meiliAttribute->attributeName ?? $propertyName;
            $propertyPath = $this->normalizePropertyPath($attributeName);
            $rawValue = $this->propertyAccessor->getValue($document, $propertyPath);
            $this->propertyAccessor->setValue($object, $propertyName, match ($meiliAttribute->relation?->type) {
                default => $this->hydratePropertyFromDocument($rawValue, $meiliAttribute),
            });
        }

        return $object;
    }

    public function hydrateDocumentFromObject(object $object): array
    {
        $metadata = $this->classMetadataRegistry->getClassMetadata($object::class);
        $document = [];
        foreach ($metadata->properties as $propertyName => $meiliAttribute) {
            $attributeName = $meiliAttribute->attributeName ?? $propertyName;
            $value = $this->propertyAccessor->getValue($object, $propertyName);
            $propertyPath = $this->normalizePropertyPath($attributeName);
            $this->propertyAccessor->setValue($document, $propertyPath, match ($meiliAttribute->relation?->type) {
                default => $this->hydrateAttributeFromObject($value, $meiliAttribute),
            });
        }

        return $document;
    }

    private function hydratePropertyFromDocument(mixed $value, AttributeMetadata $attribute): mixed
    {
        if (null !== $attribute->transformer) {
            return $attribute->transformer->toObjectProperty($value, $attribute);
        }

        foreach ($this->transformers as $transformer) {
            if ($transformer->supports($attribute)) {
                return $transformer->toObjectProperty($value, $attribute);
            }
        }

        return $value;
    }

    private function hydrateAttributeFromObject(mixed $value, AttributeMetadata $attribute): mixed
    {
        if (null !== $attribute->transformer) {
            return $attribute->transformer->toDocumentAttribute($value, $attribute);
        }

        foreach ($this->transformers as $transformer) {
            if ($transformer->supports($attribute)) {
                return $transformer->toDocumentAttribute($value, $attribute);
            }
        }

        return $value;
    }

    public function getIdFromDocument(array $data, ClassMetadata $metadata): string|int
    {
        return $this->propertyAccessor->getValue($data, $this->normalizePropertyPath($metadata->primaryKey));
    }

    public function getIdFromObject(object $object): string|int
    {
        $metadata = $this->classMetadataRegistry->getClassMetadata($object::class);

        return $this->propertyAccessor->getValue($object, $metadata->idProperty);
    }

    private function normalizePropertyPath(string $propertyPath): string
    {
        return implode('', array_map(fn (string $segment) => sprintf('[%s]', $segment), explode('.', $propertyPath)));
    }
}
