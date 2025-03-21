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

    public function hydrate(array $data, object $object, ClassMetadata $metadata): object
    {
        foreach ($metadata->properties as $propertyName => $meiliAttribute) {
            $attributeName = $meiliAttribute->attributeName ?? $propertyName;
            $propertyPath = $this->normalizePropertyPath($attributeName);
            $rawValue = $this->propertyAccessor->getValue($data, $propertyPath);
            $this->propertyAccessor->setValue($object, $propertyName, match ($meiliAttribute->relation?->type) {
                MeiliRelationType::ONE_TO_ONE => $this->fetchOneToOneRelation($meiliAttribute->relation->targetClass, $rawValue),
                default => $rawValue,
            });
        }

        return $object;
    }

    public function extractId(array $data, ClassMetadata $metadata): string|int
    {
        return $this->propertyAccessor->getValue($data, $this->normalizePropertyPath($metadata->primaryKey));
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
