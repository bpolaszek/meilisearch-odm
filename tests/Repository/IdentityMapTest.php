<?php

namespace BenTools\MeilisearchOdm\Tests\Repository;

use BenTools\MeilisearchOdm\Repository\IdentityMap;
use stdClass;

use function assert;

describe('IdentityMap', function () {
    beforeEach(function () {
        $this->identityMap = new IdentityMap();
    });

    describe('->contains()', function () {
        it('returns false when the object is not in the identity map', function () {
            $this->identityMap->clear();
            expect($this->identityMap->contains(1))->toBeFalse();
        });

        it('returns true when the object is in the identity map', function () {
            $this->identityMap->clear();
            $object = new stdClass();
            $this->identityMap->attach(1, $object);
            expect($this->identityMap->contains(1))->toBeTrue();
        });
    });

    describe('->get()', function () {
        it('returns null when the object is not in the identity map', function () {
            $this->identityMap->clear();
            expect($this->identityMap->get(1))->toBeNull();
        });

        it('returns the object when the object is in the identity map', function () {
            $this->identityMap->clear();
            $object = new stdClass();
            $this->identityMap->attach(1, $object);
            expect($this->identityMap->get(1))->toBe($object);
        });
    });

    describe('->attach()', function () {
        it('stores the object in the identity map', function () {
            $this->identityMap->clear();
            $object = new stdClass();
            $this->identityMap->attach(1, $object);
            expect($this->identityMap->get(1))->toBe($object);
        });
    });

    describe('->detach()', function () {
        it('removes the object from the identity map', function () {
            $this->identityMap->clear();
            $object = new stdClass();
            $this->identityMap->attach(1, $object);
            $this->identityMap->detach($object);
            expect($this->identityMap->contains(1))->toBeFalse();
        });
    });

    describe('->clear()', function () {
        it('clears the identity map', function () {
            $this->identityMap->clear();
            $object = new stdClass();
            $this->identityMap->attach(1, $object);
            $this->identityMap->clear();
            expect($this->identityMap->contains(1))->toBeFalse();
        });
    });

    describe('->scheduleUpsert()', function () {
        it('schedules an upsert operation', function () {
            $this->identityMap->clear();
            $object = new stdClass();
            assert(false === $this->identityMap->isScheduledForUpsert($object));
            $this->identityMap->scheduleUpsert($object);
            expect([...$this->identityMap->scheduledUpserts])->toBe([$object])
                ->and($this->identityMap->isScheduledForUpsert($object))->toBeTrue();
        });
    });

    describe('->scheduleDeletion()', function () {
        it('schedules a deletion operation', function () {
            $this->identityMap->clear();
            $object = new stdClass();
            assert(false === $this->identityMap->isScheduledForDeletion($object));
            $this->identityMap->scheduleDeletion($object);
            expect([...$this->identityMap->scheduledDeletions])->toBe([$object])
                ->and($this->identityMap->isScheduledForDeletion($object))->toBeTrue();
        });

        it('has precedence over an upsert operation', function () {
            // Upsert first, then delete
            $this->identityMap->clear();
            $object = new stdClass();
            $this->identityMap->scheduleUpsert($object);
            $this->identityMap->scheduleDeletion($object);
            expect([...$this->identityMap->scheduledUpserts])->toBe([])
                ->and([...$this->identityMap->scheduledDeletions])->toBe([$object]);

            // Delete first, then upsert
            $this->identityMap->clear();
            $object = new stdClass();
            $this->identityMap->scheduleDeletion($object);
            $this->identityMap->scheduleUpsert($object);
            expect([...$this->identityMap->scheduledUpserts])->toBe([])
                ->and([...$this->identityMap->scheduledDeletions])->toBe([$object]);
        });
    });
});
