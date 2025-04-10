<?php

namespace BenTools\MeilisearchOdm\Misc;

final readonly class Changeset
{
    /**
     * @var array<string, array{0: mixed, 1: mixed}>
     */
    public array $changedProperties;

    public function __construct(
        public array $newDocument,
        public array $previousDocument,
    ) {
        $changedProperties = [];

        foreach ($newDocument as $attribute => $newValue) {
            $oldValue = $previousDocument[$attribute] ?? null;
            if (0 !== ($oldValue <=> $newValue)) {
                $changedProperties[$attribute] = [$newValue, $oldValue];
            }
        }
        foreach ($previousDocument as $attribute => $oldValue) {
            $newValue = $newDocument[$attribute] ?? null;
            if (0 !== ($oldValue <=> $newValue)) {
                $changedProperties[$attribute] = [$newValue, $oldValue];
            }
        }

        $this->changedProperties = $changedProperties;
    }
}
