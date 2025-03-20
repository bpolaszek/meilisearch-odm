<?php

namespace BenTools\MeilisearchOdm\Metadata;

use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;
use InvalidArgumentException;

use function sprintf;

final class ClassMetadataRegistry
{
    private array $storage = [];

    public function getClassMetadata(string $className): ClassMetadata
    {
        return $this->storage[$className] ??= $this->registerClassMetadata($className);
    }

    private function registerClassMetadata(string $className): ClassMetadata
    {
        /** @var ClassMetadata $classMetadata */
        $classRefl = new \ReflectionClass($className);
        $classMetadata = (($classRefl)
            ->getAttributes(ClassMetadata::class)[0]
            ?? throw new InvalidArgumentException(
                sprintf("Class %s is not registered as a Meili Document.", $className),
            ))->newInstance();

        foreach ($classRefl->getProperties() as $propertyRefl) {
            $meiliAttribute = ($propertyRefl->getAttributes(AsMeiliAttribute::class)[0] ?? null)?->newInstance();
            if (null !== $meiliAttribute) {
                $classMetadata->registerProperty($propertyRefl->getName(), $meiliAttribute);
            }
        }

        return $classMetadata;
    }
}
