<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\Brand;

class BrandFactory
{
    public static function make(
        string $name = 'BMW',
        string $value = 'bmw',
    ): Brand {
        return new Brand(
            name: $name,
            value: $value,
        );
    }

    /**
     * @return Brand[]
     */
    public static function makeMany(int $count = 3): array
    {
        $brands = [
            ['name' => 'BMW', 'value' => 'bmw'],
            ['name' => 'Honda', 'value' => 'honda'],
            ['name' => 'Yamaha', 'value' => 'yamaha'],
            ['name' => 'Kawasaki', 'value' => 'kawasaki'],
            ['name' => 'Suzuki', 'value' => 'suzuki'],
            ['name' => 'Ducati', 'value' => 'ducati'],
            ['name' => 'KTM', 'value' => 'ktm'],
            ['name' => 'Triumph', 'value' => 'triumph'],
            ['name' => 'Harley-Davidson', 'value' => 'harley-davidson'],
            ['name' => 'Aprilia', 'value' => 'aprilia'],
        ];

        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $data = $brands[$i % count($brands)];
            $result[] = new Brand(name: $data['name'], value: $data['value']);
        }

        return $result;
    }
}
