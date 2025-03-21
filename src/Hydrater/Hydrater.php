<?php

namespace BenTools\MeilisearchOdm\Hydrater;

use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use BenTools\MeilisearchOdm\Attribute\MeiliRelationType;
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
    ) {
    }

    public function hydrateObjectFromDocument(array $document, object $object, ClassMetadata $metadata): object
    {
        foreach ($metadata->properties as $propertyName => $meiliAttribute) {
            $attributeName = $meiliAttribute->attributeName ?? $propertyName;
            $propertyPath = $this->normalizePropertyPath($attributeName);
            $rawValue = $this->propertyAccessor->getValue($document, $propertyPath);
            $this->propertyAccessor->setValue($object, $propertyName, match ($meiliAttribute->relation?->type) {
                MeiliRelationType::ONE_TO_ONE => $this->fetchOneToOneRelation($meiliAttribute->relation->targetClass, $rawValue),
                default => $rawValue,
            });
        }

        return $object;
    }

    public function hydrateDocumentFromObject(object $object, ClassMetadata $metadata): array
    {
        $document = [];
        foreach ($metadata->properties as $propertyName => $meiliAttribute) {
            $attributeName = $meiliAttribute->attributeName ?? $propertyName;
            $value = $this->propertyAccessor->getValue($object, $propertyName);
            $propertyPath = $this->normalizePropertyPath($attributeName);
            $this->propertyAccessor->setValue($document, $propertyPath, match ($meiliAttribute->relation?->type) {
                MeiliRelationType::ONE_TO_ONE => $this->getIdFromObject($value, $this->manager->classMetadataRegistry->getClassMetadata($meiliAttribute->relation->targetClass)),
                default => $value,
            });
        }

        return $document;
    }

    public function computeChangeset(object $object, ?array $document = null): array
    {
        $repository = $this->manager->getRepository($object::class);
        $metadata = $this->manager->classMetadataRegistry->getClassMetadata($object::class);
        $document ??= $this->hydrateDocumentFromObject($object, $metadata);
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

    public function getIdFromObject(object $object, ClassMetadata $metadata): string|int
    {
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
