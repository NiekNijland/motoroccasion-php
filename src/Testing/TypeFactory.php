<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Type;

class TypeFactory
{
    public static function make(
        string $name = 'R 1250 GS',
        string $value = 'r1250gs',
        ?Brand $brand = null,
    ): Type {
        return new Type(
            name: $name,
            value: $value,
            brand: $brand ?? BrandFactory::make(),
        );
    }

    /**
     * @return Type[]
     */
    public static function makeMany(int $count = 3, ?Brand $brand = null): array
    {
        $brand ??= BrandFactory::make();

        $types = [
            ['name' => 'R 1250 GS', 'value' => 'r1250gs'],
            ['name' => 'R 1300 GS', 'value' => 'r1300gs'],
            ['name' => 'F 900 R', 'value' => 'f900r'],
            ['name' => 'S 1000 RR', 'value' => 's1000rr'],
            ['name' => 'R nineT', 'value' => 'rninet'],
        ];

        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $data = $types[$i % count($types)];
            $result[] = new Type(name: $data['name'], value: $data['value'], brand: $brand);
        }

        return $result;
    }
}
