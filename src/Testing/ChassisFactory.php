<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\Chassis;

class ChassisFactory
{
    public static function make(
        ?bool $abs = null,
        ?string $frameType = null,
        ?string $frontSuspension = null,
        ?string $rearSuspension = null,
        ?string $frontBrake = null,
        ?string $rearBrake = null,
        ?string $frontTire = null,
        ?string $rearTire = null,
    ): Chassis {
        return new Chassis(
            abs: $abs,
            frameType: $frameType,
            frontSuspension: $frontSuspension,
            rearSuspension: $rearSuspension,
            frontBrake: $frontBrake,
            rearBrake: $rearBrake,
            frontTire: $frontTire,
            rearTire: $rearTire,
        );
    }
}
