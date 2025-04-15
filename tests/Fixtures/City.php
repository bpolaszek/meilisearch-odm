<?php

namespace BenTools\MeilisearchOdm\Tests\Fixtures;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument;
use BenTools\MeilisearchOdm\Attribute\MeiliRelation;
use BenTools\MeilisearchOdm\Attribute\MeiliRelationType;
use BenTools\MeilisearchOdm\Misc\Geo\GeolocatedDocumentTrait;

#[AsMeiliDocument('cities', primaryKey: 'geonameid')]
class City
{
    use GeolocatedDocumentTrait;

    #[AsMeiliAttribute('geonameid')]
    public int $id;

    #[AsMeiliAttribute]
    public string $name;

    #[AsMeiliAttribute('country code', relation: new MeiliRelation(MeiliRelationType::MANY_TO_ONE, Country::class))]
    public Country $country;

    #[AsMeiliAttribute]
    public int $population;
}
