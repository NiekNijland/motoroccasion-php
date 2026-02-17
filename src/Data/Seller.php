<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class Seller
{
    public function __construct(
        public string $name,
        public ?Province $province,
        public string $website,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $phone = null,
    ) {}

    /**
     * @param  array{name: string, province: ?string, website: string, address?: ?string, city?: ?string, phone?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        $province = isset($data['province']) ? Province::tryFrom($data['province']) : null;

        return new self(
            name: $data['name'],
            province: $province,
            website: $data['website'],
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            phone: $data['phone'] ?? null,
        );
    }

    /**
     * @return array{name: string, province: ?string, website: string, address: ?string, city: ?string, phone: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'province' => $this->province?->value,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
        ];
    }
}
