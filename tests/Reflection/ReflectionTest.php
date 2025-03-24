<?php

namespace BenTools\MeilisearchOdm\Tests\Reflection;

use BenTools\MeilisearchOdm\Manager\ObjectManager;
use BenTools\MeilisearchOdm\Misc\Reflection\Reflection;
use BenTools\MeilisearchOdm\Repository\ObjectRepository;

use function expect;

it('reflects a class and stores reflection in cache', function () {
    $refl1 = Reflection::class(ObjectManager::class);
    $refl2 = Reflection::class(ObjectRepository::class);
    $refl3 = Reflection::class(ObjectManager::class);

    expect($refl1->getName())->toBe(ObjectManager::class)
        ->and($refl2)->not()->toBe($refl1)
        ->and($refl2->getName())->toBe(ObjectRepository::class)
        ->and($refl3)->toBe($refl1);
});
