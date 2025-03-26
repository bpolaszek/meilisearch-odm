<?php

namespace BenTools\MeilisearchOdm\Attribute;

enum MeiliRelationType
{
    case MANY_TO_ONE;
    case ONE_TO_MANY;
}
