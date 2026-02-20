<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\Dimensions;

class DimensionsFactory
{
    public static function make(
        ?int $seatHeightMm = null,
        ?int $wheelbaseMm = null,
        ?int $lengthMm = null,
        ?int $widthMm = null,
        ?int $heightMm = null,
        ?float $tankCapacityLiters = null,
        ?int $weightKg = null,
    ): Dimensions {
        return new Dimensions(
            seatHeightMm: $seatHeightMm,
            wheelbaseMm: $wheelbaseMm,
            lengthMm: $lengthMm,
            widthMm: $widthMm,
            heightMm: $heightMm,
            tankCapacityLiters: $tankCapacityLiters,
            weightKg: $weightKg,
        );
    }
}
