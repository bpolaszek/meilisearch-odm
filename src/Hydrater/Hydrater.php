<?php

namespace BenTools\MeilisearchOdm\Hydrater;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute as AttributeMetadata;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use BenTools\MeilisearchOdm\Attribute\MeiliRelationType;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\DateTimeTransformer;
use BenTools\MeilisearchOdm\Hydrater\PropertyTransformer\PropertyTransformerInterface;
use BenTools\MeilisearchOdm\Manager\ObjectManager;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function array_map;
use function explode;
use function implode;
use function sprintf;

final readonly class Hydrater
{
    public function __construct(
        private ObjectManager $manager,
        private PropertyAccessorInterface $propertyAccessor = new PropertyAccessor(),
        private array $transformers = [],
    ) {
    }

    public function hydrateObjectFromDocument(array $document, object $object): object
    {
        $metadata = $this->manager->classMetadataRegistry->getClassMetadata($object::class);
        foreach ($metadata->properties as $propertyName => $meiliAttribute) {
            $attributeName = $meiliAttribute->attributeName ?? $propertyName;
            $propertyPath = $this->normalizePropertyPath($attributeName);
            $rawValue = $this->propertyAccessor->getValue($document, $propertyPath);
            $this->propertyAccessor->setValue($object, $propertyName, match ($meiliAttribute->relation?->type) {
                MeiliRelationType::ONE_TO_ONE => $this->fetchOneToOneRelation($meiliAttribute->relation->targetClass, $rawValue),
                default => $this->hydratePropertyFromDocument($rawValue, $meiliAttribute),
            });
        }

        return $object;
    }

    public function hydrateDocumentFromObject(object $object): array
    {
        $metadata = $this->manager->classMetadataRegistry->getClassMetadata($object::class);
        $document = [];
        foreach ($metadata->properties as $propertyName => $meiliAttribute) {
            $attributeName = $meiliAttribute->attributeName ?? $propertyName;
            $value = $this->propertyAccessor->getValue($object, $propertyName);
            $propertyPath = $this->normalizePropertyPath($attributeName);
            $this->propertyAccessor->setValue($document, $propertyPath, match ($meiliAttribute->relation?->type) {
                MeiliRelationType::ONE_TO_ONE => $this->getIdFromObject($value),
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

    public function computeChangeset(object $object, ?array $document = null): array
    {
        $repository = $this->manager->getRepository($object::class);
        $document ??= $this->hydrateDocumentFromObject($object);
        $rememberedState = $repository->identityMap->rememberedStates[$object] ?? [];
        $changeset = [];
        foreach ($document as $attribute => $newValue) {
            $oldValue = $rememberedState[$attribute] ?? null;
            if (0 !== ($oldValue <=> $newValue)) {
                $changeset[$attribute] = [$oldValue, $newValue];
            }
        }
        foreach ($rememberedState as $attribute => $oldValue) {
            $newValue = $document[$attribute] ?? null;
            if (0 !== ($oldValue <=> $newValue)) {
                $changeset[$attribute] = [$oldValue, $newValue];
            }
        }

        return $changeset;
    }

    public function getIdFromDocument(array $data, ClassMetadata $metadata): string|int
    {
        return $this->propertyAccessor->getValue($data, $this->normalizePropertyPath($metadata->primaryKey));
    }

    public function getIdFromObject(object $object): string|int
    {
        $metadata = $this->manager->classMetadataRegistry->getClassMetadata($object::class);
        return $this->propertyAccessor->getValue($object, $metadata->idProperty);
    }

    private function fetchOneToOneRelation(string $targetClass, string|int $id): ?object
    {
        return $this->manager->getRepository($targetClass)->find($id);
    }

    private function normalizePropertyPath(string $propertyPath): string
    {
        return implode('', array_map(fn (string $segment) => sprintf('[%s]', $segment), explode('.', $propertyPath)));
    }
}
