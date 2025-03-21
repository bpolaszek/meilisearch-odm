<?php

namespace BenTools\MeilisearchOdm\Misc\Sort;

use Stringable;

use function sprintf;

final readonly class GeoPoint implements Stringable
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('_geoPoint(%s,%s)', $this->latitude, $this->longitude);
    }
}
