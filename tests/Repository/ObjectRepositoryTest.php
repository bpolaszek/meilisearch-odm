<?php

namespace BenTools\MeilisearchOdm\Tests\Repository;

use BenTools\MeilisearchOdm\Manager\ObjectManager;
use BenTools\MeilisearchOdm\Tests\Fixtures\City;
use BenTools\MeilisearchOdm\Tests\Fixtures\Country;
use BenTools\MeilisearchOdm\Tests\Fixtures\SearchResultMockResponse;

use function BenTools\MeilisearchOdm\Tests\meili;
use function expect;
use function it;

describe('Object Repository', function () {
    $objectManager = new ObjectManager(meili()->client);
    $city = null;

    it('finds objects', function () use ($objectManager, &$city) {
        meili()->willRespond(new SearchResultMockResponse([
            [
                'geonameid' => 292223,
                'name' => 'Dubaï',
                'country code' => 'AE',
                'population' => 2956587,
            ],

        ]),
            new SearchResultMockResponse([
                [
                    'cca2' => 'AE',
                    'name' => [
                        'common' => 'United Arab Emirates',
                    ],
                    'region' => 'Asia',
                ],

            ]));
        $city = $objectManager->getRepository(City::class)->find(292223);

        expect($city)->toBeInstanceOf(City::class)
            ->and($city->id)->toBe(292223)
            ->and($city->name)->toBe('Dubaï')
            ->and($city->population)->toBe(2956587)
            ->and($city->country)->toBeInstanceOf(Country::class)
            ->and($city->country->id)->toBe('AE')
            ->and($city->country->name)->toBe('United Arab Emirates')
            ->and($city->country->region)->toBe('Asia')
        ;
    });

    it('caches objects for later reuse', function () use ($objectManager, &$city) {
        $city2 = $objectManager->getRepository(City::class)->find(292223);
        expect($city2)->toBe($city);
        expect($city2->country)->toBe($city->country);
    });

    it('returns null when not found', function () use($objectManager) {
        meili()->willRespond(new SearchResultMockResponse([]));
        $city = $objectManager->getRepository(City::class)->find(999999);
        expect($city)->toBeNull();
    });
});

