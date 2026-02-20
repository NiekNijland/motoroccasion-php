<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

enum PriceType: string
{
    case Asking = 'asking';
    case OnRequest = 'on_request';
    case Negotiable = 'negotiable';
    case Bidding = 'bidding';
}
