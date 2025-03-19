<?php

namespace BenTools\MeilisearchOdm\Tests\Hydrater;

use BenTools\MeilisearchOdm\Hydrater\Hydrater;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use BenTools\MeilisearchOdm\Tests\Fixtures\City;

it('should hydrate an object', function () {
    $data = [
        'geonameid' => '1234',
        'name' => 'Paris',
        'country_code' => 'FR',
        'population' => 2_138_551,
    ];

    $city = new City();
    $hydrater = new Hydrater();
    $registry = new ClassMetadataRegistry();

    $hydrater->hydrate($data, $city, $registry->getClassMetadata(City::class));

    expect($city->id)->toBe(1234)
        ->and($city->name)->toBe('Paris')
        ->and($city->countryCode)->toBe('FR')
        ->and($city->population)->toBe(2_138_551);
});
