<?php

namespace BenTools\MeilisearchOdm\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsMeiliDocument
{
    /**
     * @var AsMeiliAttribute[]
     */
    public array $properties = [];

    public string $className;

    public function __construct(
        public string $indexUid,
        public string $primaryKey = 'id',
    ) {
    }

    public function registerProperty(string $propertyName, AsMeiliAttribute $attribute): void
    {
        $this->properties[$propertyName] = $attribute;
    }
}
