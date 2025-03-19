<?php

namespace BenTools\MeilisearchOdm\Tests\Fixtures;

use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument;
use BenTools\MeilisearchOdm\Attribute\MeiliAttribute;

#[AsMeiliDocument('cities', primaryKey: 'geonameid')]
class City
{
    #[MeiliAttribute('geonameid')]
    public int $id;

    #[MeiliAttribute]
    public string $name;

    #[MeiliAttribute('country_code')]
    public string $countryCode;

    #[MeiliAttribute]
    public int $population;
}
