<?php

namespace BenTools\MeilisearchOdm\Misc\Geo;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;

trait GeolocatedDocumentTrait
{
    #[AsMeiliAttribute]
    public CoordinatesInterface $_geo;
}
