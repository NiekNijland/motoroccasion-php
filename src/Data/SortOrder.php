<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

enum SortOrder: string
{
    case Default = 'default';
    case RecentlyUpdated = 'update';
    case BrandAscending = 'merk-asc';
    case BrandDescending = 'merk-desc';
    case YearAscending = 'bwjr-asc';
    case YearDescending = 'bwjr-desc';
    case PriceAscending = 'pric-asc';
    case PriceDescending = 'pric-desc';
}
