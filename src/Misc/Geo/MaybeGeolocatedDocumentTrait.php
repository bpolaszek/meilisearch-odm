<?php

namespace BenTools\MeilisearchOdm\Misc\Geo;

use BenTools\MeilisearchOdm\Attribute\AsMeiliAttribute;

trait MaybeGeolocatedDocumentTrait
{
    #[AsMeiliAttribute(filterable: true, sortable: true)]
    public ?CoordinatesInterface $_geo = null;
}
