<?php

namespace BenTools\MeilisearchOdm\Metadata;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use BenTools\MeilisearchOdm\Misc\Reflection\Reflection;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;

use function array_is_list;
use function sprintf;

final class ClassMetadataRegistry
{
    /**
     * @var array<class-string, ClassMetadata>
     */
    private(set) array $storage = [];

    /**
     * @param array<class-string, ClassMetadata>|list<class-string> $configurations
     */
    public function __construct(array $configurations = [])
    {
        if (array_is_list($configurations)) {
            foreach ($configurations as $className) {
                $this->getClassMetadata($className);
            }
        } else {
            foreach ($configurations as $className => $classMetadata) {
                $this->storage[$className] = $this->populateClassMetadata(Reflection::class($className), $classMetadata);
            }
        }
    }

    public function getClassMetadata(string $className): ClassMetadata
    {
        return $this->storage[$className] ??= $this->readClassMetadata($className);
    }

    private function readClassMetadata(string $className): ClassMetadata
    {
        $classRefl = Reflection::class($className);

        /** @var ClassMetadata $classMetadata */
        $classMetadata = $this->readClassMetadataAttribute($classRefl)->newInstance();

        return $this->populateClassMetadata($classRefl, $classMetadata);
    }

    private function populateClassMetadata(ReflectionClass $classRefl, ClassMetadata $classMetadata): ClassMetadata
    {
        foreach ($classRefl->getProperties() as $propertyRefl) {
            /** @var AsMeiliAttribute $meiliAttribute */
            $meiliAttribute = ($propertyRefl->getAttributes(AsMeiliAttribute::class)[0] ?? null)?->newInstance();
            if (null !== $meiliAttribute) {
                $classMetadata->registerProperty($propertyRefl, $meiliAttribute);
            }
        }

        if (!isset($classMetadata->idProperty)) {
            throw self::noPrimaryKeyMapException($classRefl->getName(), $classMetadata);
        }

        return $classMetadata;
    }

    private function readClassMetadataAttribute(ReflectionClass $classRefl): ReflectionAttribute
    {
        return $classRefl->getAttributes(ClassMetadata::class)[0]
            ?? throw self::noMetadataException($classRefl->getName());
    }

    /**
     * @codeCoverageIgnore
     */
    private static function noMetadataException(string $className): InvalidArgumentException
    {
        return new InvalidArgumentException(
            sprintf("Class %s is not registered as a Meili Document.", $className),
        );
    }

    /**
     * @codeCoverageIgnore
     */
    private static function noPrimaryKeyMapException(string $className, ClassMetadata $metadata): InvalidArgumentException
    {
        return new InvalidArgumentException(
            sprintf("Class %s has no property map to primary key %s.", $className, $metadata->primaryKey),
        );
    }
}
