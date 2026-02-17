<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class Type
{
    public function __construct(
        public string $name,
        public string $value,
        public Brand $brand,
    ) {
    }
}
