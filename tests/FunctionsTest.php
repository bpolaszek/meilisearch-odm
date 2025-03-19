<?php

namespace BenTools\MeilisearchOdm\Tests;

use function Bentools\MeilisearchFilters\field;
use function BenTools\MeilisearchOdm\resolve_filters;
use function BenTools\MeilisearchOdm\weakmap_objects;
use function BenTools\MeilisearchOdm\weakmap_values;
use function describe;
use function expect;
use function it;

describe('weakmap_objects()', function () {
    it('returns objects from a weakmap', function () {
        $foo = new \stdClass();
        $bar = new \stdClass();
        $weakmap = new \WeakMap();
        $weakmap[$foo] = null;
        $weakmap[$bar] = null;
        expect(weakmap_objects($weakmap))->toBeIterable()
            ->and([...weakmap_objects($weakmap)])->toBe([$foo, $bar]);
    });
});


describe('weakmap_values()', function () {
    it('returns values from a weakmap', function () {
        $foo = new \stdClass();
        $bar = new \stdClass();
        $baz = new \stdClass();
        $weakmap = new \WeakMap();
        $weakmap[$foo] = 'foo';
        $weakmap[$bar] = 'bar';
        $weakmap[$baz] = 'foo';
        expect(weakmap_values($weakmap))->toBeIterable()
            ->and([...weakmap_values($weakmap)])->toBe(['foo', 'bar']);
    });
});

describe('resolve_filters()', function () {
    it('processes an associative array', function () {
        $filters = [
            'foo' => 'bar',
            'baz' => 'qux',
        ];
        $expressions = resolve_filters($filters);
        expect($expressions)->toBeArray()
            ->and($expressions)->toHaveCount(2)
            ->and($expressions)->toEqual([
                field('foo')->isIn(['bar']),
                field('baz')->isIn(['qux']),
            ]);
    });

    it('processes an array of expressions', function () {
        $filters = [
            field('foo')->isIn(['bar']),
            field('baz')->isIn(['qux']),
        ];
        $expressions = resolve_filters($filters);
        expect($expressions)->toBeArray()
            ->and($expressions)->toHaveCount(2)
            ->and($expressions)->toEqual($filters);
    });

    it('processes a single expression', function () {
        $filter = field('foo')->isIn(['bar']);
        $expressions = resolve_filters($filter);
        expect($expressions)->toBeArray()
            ->and($expressions)->toHaveCount(1)
            ->and($expressions)->toEqual([$filter]);
    });
});
