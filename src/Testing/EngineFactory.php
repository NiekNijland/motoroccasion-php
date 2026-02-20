<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\Engine;

class EngineFactory
{
    public static function make(
        ?int $capacityCc = null,
        ?string $type = null,
        ?int $cylinders = null,
        ?int $valves = null,
        ?string $boreAndStroke = null,
        ?string $compressionRatio = null,
        ?string $fuelDelivery = null,
        ?string $fuelType = null,
        ?bool $isElectric = null,
        ?string $ignition = null,
        ?string $maxTorque = null,
        ?string $clutch = null,
        ?string $gearbox = null,
        ?string $driveType = null,
        ?string $starter = null,
        ?string $topSpeed = null,
    ): Engine {
        return new Engine(
            capacityCc: $capacityCc,
            type: $type,
            cylinders: $cylinders,
            valves: $valves,
            boreAndStroke: $boreAndStroke,
            compressionRatio: $compressionRatio,
            fuelDelivery: $fuelDelivery,
            fuelType: $fuelType,
            isElectric: $isElectric,
            ignition: $ignition,
            maxTorque: $maxTorque,
            clutch: $clutch,
            gearbox: $gearbox,
            driveType: $driveType,
            starter: $starter,
            topSpeed: $topSpeed,
        );
    }
}
