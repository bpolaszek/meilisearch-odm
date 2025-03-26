<?php

namespace BenTools\MeilisearchOdm\Misc\Reflection;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use WeakMap;

use function array_all;
use function ltrim;

final class Reflection
{
    private static self $instance;
    private array $reflectionClassCache = [];
    private array $reflectionPropertyCache = [];
    private array $reflectionMethodCache = [];
    private WeakMap $reflectionTypesCache;

    private function __construct()
    {
        $this->reflectionTypesCache = new WeakMap();
    }

    private static function get(): self
    {
        return self::$instance ??= new self();
    }

    public static function class(object|string $class): ReflectionClass
    {
        $className = is_object($class) ? $class::class : $class;

        return self::get()->reflectionClassCache[$className] ??= new ReflectionClass($className);
    }

    public static function property(object|string $class, string $property): ReflectionProperty
    {
        $className = is_object($class) ? $class::class : $class;

        return self::get()->reflectionPropertyCache[$className][$property] ??= self::class($class)->getProperty($property);
    }

    public static function method(object|string $class, string $method): ReflectionMethod
    {
        $className = is_object($class) ? $class::class : $class;

        return self::get()->reflectionMethodCache[$className][$method] ??= self::class($class)->getMethod($method);
    }

    public static function getBestClassForProperty(ReflectionProperty $property, array $classNames): string
    {
        return array_find($classNames, fn ($className) => self::isPropertyCompatible($property, $className))
            ?? throw new InvalidArgumentException("No compatible class found for property {$property->getName()}");
    }

    public static function isPropertyCompatible(ReflectionProperty $property, string $className): bool
    {
        $settableType = $property->getSettableType();

        return self::isTypeCompatible($settableType, $className);
    }

    public static function isTypeCompatible(ReflectionType $type, string $className): bool
    {
        return self::get()->reflectionTypesCache[$type] ??= match ($type::class) {
            ReflectionNamedType::class => self::isCompatibleWithNamedType($type, $className),
            ReflectionUnionType::class => self::isCompatibleWithUnionType($type, $className),
            ReflectionIntersectionType::class => self::isCompatibleWithIntersectionType($type, $className),
            default => false,
        };
    }

    private static function isCompatibleWithNamedType(ReflectionNamedType $type, string $className): bool
    {
        return !$type->isBuiltin() && is_a($className, ltrim($type->getName(), '?'), true);
    }

    private static function isCompatibleWithIntersectionType(ReflectionIntersectionType $type, string $className): bool
    {
        return array_any(
            $type->getTypes(),
            fn ($intersectionType) => self::isTypeCompatible($intersectionType, $className)
        );
    }

    private static function isCompatibleWithUnionType(ReflectionUnionType $type, string $className): bool
    {
        return array_all($type->getTypes(), fn ($unionType) => self::isTypeCompatible($unionType, $className));
    }
}
