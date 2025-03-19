<?php

namespace BenTools\MeilisearchOdm\Tests\Metadata;

use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use BenTools\MeilisearchOdm\Tests\Fixtures\City;

it('registers a class as a Meili Document', function () {
    $registry = new ClassMetadataRegistry();
    $metadata = $registry->getClassMetadata(City::class);
    expect($metadata)->toBeInstanceOf(AsMeiliDocument::class);
});
