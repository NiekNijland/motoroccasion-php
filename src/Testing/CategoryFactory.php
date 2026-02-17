<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\Category;

class CategoryFactory
{
    public static function make(
        string $name = 'Naked',
        string $value = '43',
    ): Category {
        return new Category(
            name: $name,
            value: $value,
        );
    }

    /**
     * @return Category[]
     */
    public static function makeMany(int $count = 3): array
    {
        $categories = [
            ['name' => 'Naked', 'value' => '43'],
            ['name' => 'Sport', 'value' => '4'],
            ['name' => 'Toer', 'value' => '6'],
            ['name' => 'All Off Road', 'value' => '1'],
            ['name' => 'Custom Cruiser', 'value' => '8'],
            ['name' => 'Enduro', 'value' => '41'],
            ['name' => 'Scooter', 'value' => '5'],
        ];

        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $data = $categories[$i % count($categories)];
            $result[] = new Category(name: $data['name'], value: $data['value']);
        }

        return $result;
    }
}
