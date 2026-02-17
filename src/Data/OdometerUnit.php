<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

enum OdometerUnit: string
{
    case Kilometers = 'KM';
    case Miles = 'MI';
}
