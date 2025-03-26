<?php

namespace BenTools\MeilisearchOdm\Misc\Geo;

final class Coordinates implements CoordinatesInterface
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }
}
