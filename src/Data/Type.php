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

    /**
     * @param array{name: string, value: string, brand: array{name: string, value: string}} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            value: $data['value'],
            brand: Brand::fromArray($data['brand']),
        );
    }

    /**
     * @return array{name: string, value: string, brand: array{name: string, value: string}}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'brand' => $this->brand->toArray(),
        ];
    }
}
