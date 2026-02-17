<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

use NiekNijland\MotorOccasion\Exception\MotorOccasionException;
use ValueError;

readonly class Result
{
    public function __construct(
        public string $brand,
        public string $model,
        public int $price,
        public int $year,
        public int $odometerReading,
        public OdometerUnit $odometerReadingUnit,
        public string $image,
        public string $url,
        public Seller $seller,
        public ?int $id = null,
        public ?int $originalPrice = null,
        public ?int $monthlyLease = null,
    ) {
    }

    /**
     * @param array{brand: string, model: string, price: int, year: int, odometerReading: int, odometerReadingUnit: string, image: string, url: string, seller: array{name: string, province: ?string, website: string, address?: ?string, city?: ?string, phone?: ?string}, id?: ?int, originalPrice?: ?int, monthlyLease?: ?int} $data
     *
     * @throws MotorOccasionException
     */
    public static function fromArray(array $data): self
    {
        try {
            $odometerUnit = OdometerUnit::from($data['odometerReadingUnit']);
        } catch (ValueError $valueError) {
            throw new MotorOccasionException(
                'Invalid odometer unit: ' . $data['odometerReadingUnit'],
                previous: $valueError,
            );
        }

        return new self(
            brand: $data['brand'],
            model: $data['model'],
            price: $data['price'],
            year: $data['year'],
            odometerReading: $data['odometerReading'],
            odometerReadingUnit: $odometerUnit,
            image: $data['image'],
            url: $data['url'],
            seller: Seller::fromArray($data['seller']),
            id: $data['id'] ?? null,
            originalPrice: $data['originalPrice'] ?? null,
            monthlyLease: $data['monthlyLease'] ?? null,
        );
    }

    /**
     * @return array{brand: string, model: string, price: int, year: int, odometerReading: int, odometerReadingUnit: string, image: string, url: string, seller: array{name: string, province: ?string, website: string, address: ?string, city: ?string, phone: ?string}, id: ?int, originalPrice: ?int, monthlyLease: ?int}
     */
    public function toArray(): array
    {
        return [
            'brand' => $this->brand,
            'model' => $this->model,
            'price' => $this->price,
            'year' => $this->year,
            'odometerReading' => $this->odometerReading,
            'odometerReadingUnit' => $this->odometerReadingUnit->value,
            'image' => $this->image,
            'url' => $this->url,
            'seller' => $this->seller->toArray(),
            'id' => $this->id,
            'originalPrice' => $this->originalPrice,
            'monthlyLease' => $this->monthlyLease,
        ];
    }
}
