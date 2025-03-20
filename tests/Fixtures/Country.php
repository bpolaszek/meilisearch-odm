<?php

namespace BenTools\MeilisearchOdm\Tests\Fixtures;

use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument;
use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;

#[AsMeiliDocument('countries', primaryKey: 'cca2')]
class Country
{
    #[AsMeiliAttribute('cca2')]
    public string $id;

    #[AsMeiliAttribute('name.common')]
    public string $name;

    #[AsMeiliAttribute]
    public string $region;
}
