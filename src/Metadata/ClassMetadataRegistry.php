<?php

namespace BenTools\MeilisearchOdm\Metadata;

use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;

use InvalidArgumentException;

use function sprintf;

final class ClassMetadataRegistry
{
    private array $storage = [];

    public function getClassMetadata(string $className): ClassMetadata
    {
        return $this->storage[$className] ??= ((new \ReflectionClass($className))
            ->getAttributes(ClassMetadata::class)[0]
            ?? throw new InvalidArgumentException(
                sprintf("Class %s is not registered as a Meili Document.", $className),
            ))->newInstance();
    }
}
