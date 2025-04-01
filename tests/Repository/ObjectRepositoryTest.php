<?php

namespace BenTools\MeilisearchOdm\Tests\Repository;

use BenTools\MeilisearchOdm\Manager\ObjectManager;
use BenTools\MeilisearchOdm\Misc\Reflection\Reflection;
use BenTools\MeilisearchOdm\Misc\Sort\GeoPoint;
use BenTools\MeilisearchOdm\Misc\Sort\Sort;
use BenTools\MeilisearchOdm\Misc\Sort\SortDirection;
use BenTools\MeilisearchOdm\Repository\ObjectRepository;
use BenTools\MeilisearchOdm\Tests\Fixtures\City;
use BenTools\MeilisearchOdm\Tests\Fixtures\Country;
use BenTools\MeilisearchOdm\Tests\Fixtures\SearchResultMockResponse;

use function array_map;
use function Bentools\MeilisearchFilters\field;
use function BenTools\MeilisearchOdm\Tests\meili;
use function describe;
use function expect;
use function it;

describe('ObjectRepository->find()', function () {
    $objectManager = new ObjectManager(meili()->client);
    $city = null;

    it('finds objects', function () use ($objectManager, &$city) {
        meili()->willRespond(new SearchResultMockResponse([
            [
                'geonameid' => 292223,
                'name' => 'Dubaï',
                'country code' => 'AE',
                'population' => 2956587,
                '_geo' => [
                    'lat' => 25.07725,
                    'lng' => 55.30927,
                ],
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
            ->and($city->country->region)->toBe('Asia');
    });

    it('caches objects for later reuse', function () use ($objectManager, &$city) {
        $city2 = $objectManager->getRepository(City::class)->find(292223);
        expect($city2)->toBe($city)
            ->and($city2->country)->toBe($city->country);
    });

    it('returns null when not found', function () use ($objectManager) {
        meili()->willRespond(new SearchResultMockResponse([]));
        $city = $objectManager->getRepository(City::class)->find(999999);
        expect($city)->toBeNull();
    });
});

describe('ObjectRepository->findOneBy()', function () {
    $objectManager = new ObjectManager(meili()->client);
    $city = null;

    it('finds objects', function () use ($objectManager, &$city) {
        meili()->willRespond(new SearchResultMockResponse([
            [
                'geonameid' => 292223,
                'name' => 'Dubaï',
                'country code' => 'AE',
                'population' => 2956587,
                '_geo' => [
                    'lat' => 25.07725,
                    'lng' => 55.30927,
                ],
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
        $city = $objectManager->getRepository(City::class)->findOneBy(['geonameid' => 292223]);

        expect($city)->toBeInstanceOf(City::class)
            ->and($city->id)->toBe(292223)
            ->and($city->name)->toBe('Dubaï')
            ->and($city->population)->toBe(2956587)
            ->and($city->country)->toBeInstanceOf(Country::class)
            ->and($city->country->id)->toBe('AE')
            ->and($city->country->name)->toBe('United Arab Emirates')
            ->and($city->country->region)->toBe('Asia');
    });

    it('caches objects for later reuse', function () use ($objectManager, &$city) {
        meili()->willRespond(new SearchResultMockResponse([
            [
                'geonameid' => 292223,
                'name' => 'Dubaï',
                'country code' => 'AE',
                'population' => 2956587,
                '_geo' => [
                    'lat' => 25.07725,
                    'lng' => 55.30927,
                ],
            ],

        ]));
        $city2 = $objectManager->getRepository(City::class)->findOneBy(['geonameid' => 292223]);
        expect($city2)->toBe($city)
            ->and($city2->country)->toBe($city->country);
    });

    it('returns null when not found', function () use ($objectManager) {
        meili()->willRespond(new SearchResultMockResponse([]));
        $city = $objectManager->getRepository(City::class)->findOneBy(['geonameid' => 999999]);
        expect($city)->toBeNull();
    });
});

describe('ObjectRepository->clear()', function () {
    $objectManager = new ObjectManager(meili()->client);
    $country = null;

    it('clears the identity map', function () use ($objectManager, &$country) {
        meili()->willRespond(new SearchResultMockResponse([
            [
                'cca2' => 'AE',
                'name' => [
                    'common' => 'United Arab Emirates',
                ],
                'region' => 'Asia',
            ],

        ]));
        $country = $objectManager->getRepository(Country::class)->find('AE');
        $objectManager->getRepository(Country::class)->clear();

        meili()->willRespond(new SearchResultMockResponse([
            [
                'cca2' => 'AE',
                'name' => [
                    'common' => 'United Arab Emirates',
                ],
                'region' => 'Asia',
            ],

        ]));
        $country2 = $objectManager->getRepository(Country::class)->find('AE');
        expect($country2)->not->toBe($country);
    });
});

describe('ObjectRepository->resolveFilters()', function () {
    $refl = Reflection::class(ObjectRepository::class);
    $repository = $refl->newInstanceWithoutConstructor();
    $resolveFilters = fn ($input) => $refl->getMethod('resolveFilters')->invokeArgs($repository, [$input]);

    it('processes an associative array', function () use ($resolveFilters) {
        $filters = [
            'foo' => 'bar',
            'baz' => 'qux',
        ];
        $expressions = $resolveFilters($filters);
        expect($expressions)->toBeArray()
            ->and($expressions)->toHaveCount(2)
            ->and($expressions)->toEqual([
                field('foo')->isIn(['bar']),
                field('baz')->isIn(['qux']),
            ]);
    });

    it('processes an array of expressions', function () use ($resolveFilters) {
        $filters = [
            field('foo')->isIn(['bar']),
            field('baz')->isIn(['qux']),
        ];
        $expressions = $resolveFilters($filters);
        expect($expressions)->toBeArray()
            ->and($expressions)->toHaveCount(2)
            ->and($expressions)->toEqual($filters);
    });

    it('processes a single expression', function () use ($resolveFilters) {
        $filter = field('foo')->isIn(['bar']);
        $expressions = $resolveFilters($filter);
        expect($expressions)->toBeArray()
            ->and($expressions)->toHaveCount(1)
            ->and($expressions)->toEqual([$filter]);
    });
});

describe('ObjectRepository->resolveSorts()', function () {
    $refl = Reflection::class(ObjectRepository::class);
    $repository = $refl->newInstanceWithoutConstructor();
    $resolveSorts = fn ($input) => $refl->getMethod('resolveSorts')->invokeArgs($repository, [$input]);

    it('processes an associative array', function () use ($resolveSorts) {
        $sorts = [
            'foo' => 'asc',
            'baz' => 'desc',
        ];
        $sorts = $resolveSorts($sorts);
        expect($sorts)->toBeArray()
            ->and($sorts)->toHaveCount(2)
            ->and($sorts)->toEqual([
                new Sort('foo', 'asc'),
                new Sort('baz', 'desc'),
            ]);
    });

    it('processes an array of sorts', function () use ($resolveSorts) {
        $sorts = [
            new Sort('foo', 'asc'),
            new Sort('baz', 'desc'),
        ];
        $sorts = $resolveSorts($sorts);
        expect($sorts)->toBeArray()
            ->and($sorts)->toHaveCount(2)
            ->and($sorts)->toEqual($sorts);
    });

    it('processes a single sort', function () use ($resolveSorts) {
        $sort = new Sort('foo', 'asc');
        $sorts = $resolveSorts($sort);
        expect($sorts)->toBeArray()
            ->and($sorts)->toHaveCount(1)
            ->and($sorts)->toEqual([$sort]);
    });

    it('stringifies sorts', function () {
        $sorts = [
            new Sort(new GeoPoint(48.8561446, 2.2978204), SortDirection::DESC),
            new Sort('name', 'asc'),
        ];

        expect(array_map('strval', $sorts))->toBe([
            '_geoPoint(48.8561446,2.2978204):desc',
            'name:asc',
        ]);
    });
});
