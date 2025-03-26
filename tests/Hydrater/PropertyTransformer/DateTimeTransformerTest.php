<?php

namespace BenTools\MeilisearchOdm\Tests\Hydrater\PropertyTransformer;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument;
use BenTools\MeilisearchOdm\Manager\ObjectManager;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use DateTimeImmutable;
use DateTimeInterface;

use function describe;
use function expect;
use function test;

describe('DateTimeTransformer', function () {
    $object = new class {
        #[AsMeiliAttribute]
        public ?int $id;

        #[AsMeiliAttribute]
        public ?DateTimeInterface $createdAt;
    };

    test('->toObjectProperty()', function () use ($object) {
        $objectManager = new ObjectManager(classMetadataRegistry: new ClassMetadataRegistry([
            $object::class => new AsMeiliDocument('whatever')
        ]));
        $createdAt = new DateTimeImmutable('2025-01-01T00:00:00Z');
        $object = $objectManager->hydrater->hydrateObjectFromDocument(['createdAt' => $createdAt->getTimestamp()], $object);
        expect($object->createdAt)->toEqual($createdAt);
    });

    test('->toDocumentAttribute()', function () use ($object) {
        $objectManager = new ObjectManager(classMetadataRegistry: new ClassMetadataRegistry([
            $object::class => new AsMeiliDocument('whatever')
        ]));
        $createdAt = new DateTimeImmutable('2025-01-01T00:00:00Z');
        $document = $objectManager->hydrater->hydrateDocumentFromObject($object);
        expect($document['createdAt'])->toEqual($createdAt->getTimestamp());
    });
});
