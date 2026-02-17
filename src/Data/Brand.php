<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class Brand
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }

    /**
     * @param array{name: string, value: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            value: $data['value'],
        );
    }

    /**
     * @return array{name: string, value: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
        ];
    }
}
