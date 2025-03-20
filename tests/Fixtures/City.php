<?php

namespace BenTools\MeilisearchOdm\Tests\Fixtures;

use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument;
use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;
use BenTools\MeilisearchOdm\Attribute\MeiliRelation;
use BenTools\MeilisearchOdm\Attribute\MeiliRelationType;

#[AsMeiliDocument('cities', primaryKey: 'geonameid')]
class City
{
    #[AsMeiliAttribute('geonameid')]
    public int $id;

    #[AsMeiliAttribute]
    public string $name;

    #[AsMeiliAttribute('country code', relation: new MeiliRelation(Country::class, MeiliRelationType::ONE_TO_ONE))]
    public Country $country;

    #[AsMeiliAttribute]
    public int $population;
}
