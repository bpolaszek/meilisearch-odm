<?php

namespace BenTools\MeilisearchOdm\Misc\Reflection;

use ReflectionClass;

final class Reflection
{
    private static array $cache = [];

    public static function class(object|string $class): ReflectionClass
    {
        $className = is_object($class) ? $class::class : $class;

        return self::$cache[$className] ??= new ReflectionClass($className);
    }
}
