<?php

namespace BenTools\MeilisearchOdm\Misc\Sort;

use Stringable;

use function sprintf;

final readonly class Sort implements Stringable
{
    public const SortDirection ASC = SortDirection::ASC;
    public const SortDirection DESC = SortDirection::DESC;

    public SortDirection $direction;

    public function __construct(
        public string|GeoPoint $field,
        string|SortDirection $direction = self::ASC,
    ) {
        $this->direction = $direction instanceof SortDirection ? $direction : SortDirection::from($direction);
    }

    public function __toString(): string
    {
        return sprintf('%s:%s', $this->field, $this->direction->value);
    }
}
