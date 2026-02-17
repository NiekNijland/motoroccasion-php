<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class Category
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }
}
