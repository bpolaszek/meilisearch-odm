<?php

namespace BenTools\MeilisearchOdm\Misc\Geo;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;

trait MaybeGeolocatedDocumentTrait
{
    #[AsMeiliAttribute]
    public ?CoordinatesInterface $_geo = null;
}
