<?php

namespace BenTools\MeilisearchOdm\Hydrater;

use AutoMapper\AutoMapper;
use AutoMapper\AutoMapperInterface;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;

final readonly class Hydrater
{
    private AutoMapperInterface $autoMapper;

    public function __construct(
        ?AutoMapperInterface $autoMapper = null,
    ) {
        $this->autoMapper = $autoMapper ?? AutoMapper::create();
    }

    public function hydrate(array $data, object $object, ClassMetadata $metadata): object
    {
        $middle = [];
        foreach ($metadata->properties as $propertyName => $meiliAttribute) {
            $attributeName = $meiliAttribute->name ?? $propertyName;
            if (isset($data[$attributeName])) {
                $middle[$propertyName] = $data[$attributeName];
            }
        }

        return $this->autoMapper->map($middle, $object);
    }
}
