<?php

namespace BenTools\MeilisearchOdm\Misc;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;
use BenTools\MeilisearchOdm\Metadata\ClassMetadataRegistry;
use Bentools\Set\Set;
use Closure;
use Exception;
use Meilisearch\Client;

use function array_values;
use function BenTools\IterableFunctions\iterable;

final readonly class SchemaUpdater
{
    public function __construct(
        private Client $meili,
        private ClassMetadataRegistry $registry,
    ) {
    }

    public function updateSchema(?Closure $onProgress = null): void
    {
        $onProgress ??= fn () => null;
        foreach ($this->registry->storage as $class => $metadata) {
            $task = $this->meili->createIndex($metadata->indexUid, ['primaryKey' => $metadata->primaryKey]);
            $this->meili->waitForTask($task['taskUid']);
            $shouldBeFilterableAttributes = [
                $metadata->primaryKey,
                ...iterable(array_values($metadata->properties))
                    ->filter(function (AsMeiliAttribute $attribute) {
                    return null !== $attribute->relation
                        || true === $attribute->filterable;
                })
                    ->map(fn (AsMeiliAttribute $attr) => $attr->attributeName ?? $attr->property->getName())
            ];
            $shouldBeSortableAttributes = [
                $metadata->primaryKey,
                ...iterable(array_values($metadata->properties))
                    ->filter(function (AsMeiliAttribute $attribute) {
                    return true === $attribute->sortable;
                })
                    ->map(fn (AsMeiliAttribute $attr) => $attr->attributeName ?? $attr->property->getName())
            ];
            $existingFilterableAttributes = $this->meili->index($metadata->indexUid)->getFilterableAttributes();
            $task = $this->meili->index($metadata->indexUid)->updateFilterableAttributes([
                ...new Set($existingFilterableAttributes, $shouldBeFilterableAttributes),
            ]);
            $this->meili->waitForTask($task['taskUid']);
            $existingSortableAttributes = $this->meili->index($metadata->indexUid)->getSortableAttributes();
            $task = $this->meili->index($metadata->indexUid)->updateSortableAttributes([
                ...new Set($existingSortableAttributes, $shouldBeSortableAttributes),
            ]);
            $this->meili->waitForTask($task['taskUid']);
            $onProgress($class, $metadata);
        }
    }

    public function dropSchema(?Closure $onProgress = null): void
    {
        $onProgress ??= fn () => null;
        foreach ($this->registry->storage as $class => $metadata) {
            if (!$this->indexExists($metadata->indexUid)) {
                goto Next;
            }
            $task = $this->meili->deleteIndex($metadata->indexUid);
            $this->meili->waitForTask($task['taskUid']);
            Next:
            $onProgress($class, $metadata);
        }
    }

    private function indexExists(string $indexUid): bool
    {
        try {
            $this->meili->getIndex($indexUid);
        } catch (Exception) {
            return false;
        }

        return true;
    }
}
