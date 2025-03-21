<?php

namespace BenTools\MeilisearchOdm\Metadata;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;

use function sprintf;

final class ClassMetadataRegistry
{
    /**
     * @var array<class-string, ClassMetadata>
     */
    private array $storage = [];

    public function __construct(array $configurations = [])
    {
        $configurations = (fn (ClassMetadata ...$configurations) => $configurations)(...$configurations);
        foreach ($configurations as $configuration) {
            $this->storage[$configuration->className] = $configuration; // @codeCoverageIgnore
        }
    }

    public function getClassMetadata(string $className): ClassMetadata
    {
        return $this->storage[$className] ??= $this->readClassMetadata($className);
    }

    private function readClassMetadata(string $className): ClassMetadata
    {
        $classRefl = new ReflectionClass($className);

        /** @var ClassMetadata $classMetadata */
        $classMetadata = $this->readClassMetadataAttribute($classRefl)->newInstance();
        $classMetadata->className = $className;

        foreach ($classRefl->getProperties() as $propertyRefl) {
            /** @var AsMeiliAttribute $meiliAttribute */
            $meiliAttribute = ($propertyRefl->getAttributes(AsMeiliAttribute::class)[0] ?? null)?->newInstance();
            if (null !== $meiliAttribute) {
                $classMetadata->registerProperty($propertyRefl->getName(), $meiliAttribute);
                if ($meiliAttribute->attributeName === $classMetadata->primaryKey) {
                    $classMetadata->idProperty = $propertyRefl->getName();
                }
            }
        }

        $classMetadata->idProperty ??= throw self::noPrimaryKeyMapException($className, $classMetadata);

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
