<?php

namespace BenTools\MeilisearchOdm\Attribute;

use Attribute;

/**
 * @codeCoverageIgnore
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsMeiliDocument
{
    /**
     * @var MeiliAttribute[]
     */
    public array $properties = [];

    public function __construct(
        public string $indexUid,
        public string $primaryKey = 'id',
    ) {
    }

    public function registerProperty(string $propertyName, MeiliAttribute $attribute): void
    {
        $this->properties[$propertyName] = $attribute;
    }
}
