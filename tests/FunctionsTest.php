<?php

namespace BenTools\MeilisearchOdm\Tests;

use stdClass;
use WeakMap;

use function BenTools\MeilisearchOdm\weakmap_objects;
use function BenTools\MeilisearchOdm\weakmap_values;
use function describe;
use function expect;
use function it;

describe('weakmap_objects()', function () {
    it('returns objects from a weakmap', function () {
        $foo = new stdClass();
        $bar = new stdClass();
        $weakmap = new WeakMap();
        $weakmap[$foo] = null;
        $weakmap[$bar] = null;
        expect(weakmap_objects($weakmap))->toBeIterable()
            ->and([...weakmap_objects($weakmap)])->toBe([$foo, $bar]);
    });
});

describe('weakmap_values()', function () {
    it('returns values from a weakmap', function () {
        $foo = new stdClass();
        $bar = new stdClass();
        $baz = new stdClass();
        $weakmap = new WeakMap();
        $weakmap[$foo] = 'foo';
        $weakmap[$bar] = 'bar';
        $weakmap[$baz] = 'foo';
        expect(weakmap_values($weakmap))->toBeIterable()
            ->and([...weakmap_values($weakmap)])->toBe(['foo', 'bar']);
    });
});
