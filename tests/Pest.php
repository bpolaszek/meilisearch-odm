<?php

namespace BenTools\MeilisearchOdm\Tests;

use BenTools\MeilisearchOdm\Tests\Fixtures\MeiliMock;

function meili(): MeiliMock
{
    return MeiliMock::get();
}
