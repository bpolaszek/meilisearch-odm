<?php

namespace BenTools\MeilisearchOdm\Repository;

use BenTools\MeilisearchOdm\Manager\ObjectManager;

use function Bentools\MeilisearchFilters\field;

final readonly class ObjectRepository
{
    private IdentityMap $identityMap;

    public function __construct(
        private ObjectManager $objectManager,
        private string $className,
    ) {
        $this->identityMap = new IdentityMap();
    }

    public function find(mixed $id): ?object
    {
        if ($this->identityMap->contains($id)) {
            return $this->identityMap->get($id);
        }

        $metadata = $this->objectManager->classMetadataRegistry->getClassMetadata($this->className);

        $searchResult = $this->objectManager->meili->index($metadata->indexUid)->search('', [
            'filter' => (string) field($metadata->primaryKey)->equals($id),
            'limit' => 1,
        ]);

        $documents = [...$searchResult];

        if (!isset($documents[0])) {
            return null;
        }

        $object = $this->objectManager->hydrater->hydrate($documents[0], new $this->className(), $metadata);
        $this->identityMap->store($id, $object);

        return $object;
    }
}
