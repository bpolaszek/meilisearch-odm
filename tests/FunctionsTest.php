<?php

namespace BenTools\MeilisearchOdm\Tests;

use BenTools\MeilisearchOdm\Misc\Sort\GeoPoint;
use BenTools\MeilisearchOdm\Misc\Sort\Sort;

use BenTools\MeilisearchOdm\Misc\Sort\SortDirection;

use function array_map;
use function Bentools\MeilisearchFilters\field;
use function BenTools\MeilisearchOdm\resolve_filters;
use function BenTools\MeilisearchOdm\resolve_sorts;
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

describe('resolve_sorts()', function () {
    it('processes an associative array', function () {
        $sorts = [
            'foo' => 'asc',
            'baz' => 'desc',
        ];
        $sorts = resolve_sorts($sorts);
        expect($sorts)->toBeArray()
            ->and($sorts)->toHaveCount(2)
            ->and($sorts)->toEqual([
                new Sort('foo', 'asc'),
                new Sort('baz', 'desc'),
            ]);
    });

    it('processes an array of sorts', function () {
        $sorts = [
            new Sort('foo', 'asc'),
            new Sort('baz', 'desc'),
        ];
        $sorts = resolve_sorts($sorts);
        expect($sorts)->toBeArray()
            ->and($sorts)->toHaveCount(2)
            ->and($sorts)->toEqual($sorts);
    });

    it('processes a single sort', function () {
        $sort = new Sort('foo', 'asc');
        $sorts = resolve_sorts($sort);
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
