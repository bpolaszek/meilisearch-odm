<?php

namespace BenTools\MeilisearchOdm\Tests\Hydrater;

use BenTools\MeilisearchOdm\Hydrater\Hydrater;
use BenTools\MeilisearchOdm\Manager\ObjectManager;
use BenTools\MeilisearchOdm\Tests\Fixtures\Country;

use function describe;
use function expect;
use function test;

describe('Hydrater', function () {
    test('hydrate()', function () {
        $data = [
            'cca2' => 'AE',
            'name' => [
                'common' => 'United Arab Emirates',
            ],
            'region' => 'Asia',
        ];

        $objectManager = new ObjectManager();
        $hydrater = new Hydrater($objectManager);

        $country = $hydrater->hydrate(
            $data,
            new Country(),
            $objectManager->classMetadataRegistry->getClassMetadata(Country::class),
        );

        expect($country)->toBeInstanceOf(Country::class)
            ->and($country->id)->toBe('AE')
            ->and($country->name)->toBe('United Arab Emirates')
            ->and($country->region)->toBe('Asia');
    });

    test('getIdFromDocument()', function () {
        $data = [
            'cca2' => 'AE',
            'name' => [
                'common' => 'United Arab Emirates',
            ],
            'region' => 'Asia',
        ];

        $objectManager = new ObjectManager();
        $hydrater = new Hydrater($objectManager);

        $id = $hydrater->getIdFromDocument(
            $data,
            $objectManager->classMetadataRegistry->getClassMetadata(Country::class),
        );

        expect($id)->toBe('AE');
    });

    test('getIdFromObject()', function () {
        $country = new Country();
        $country->id = 'AE';

        $objectManager = new ObjectManager();
        $hydrater = new Hydrater($objectManager);

        $id = $hydrater->getIdFromObject(
            $country,
            $objectManager->classMetadataRegistry->getClassMetadata(Country::class),
        );

        expect($id)->toBe('AE');
    });
});
