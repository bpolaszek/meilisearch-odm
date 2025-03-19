<?php

namespace BenTools\MeilisearchOdm\Tests\Fixtures;

use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument;

#[AsMeiliDocument('cities', primaryKey: 'geonameid')]
class City
{
    public int $geonameid;
    public string $name;
    public string $country_code;
}
