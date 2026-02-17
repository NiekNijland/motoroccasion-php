<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class Result
{
    public function __construct(
        public string $brand,
        public string $model,
        public int $price,
        public int $year,
        public int $odometerReading,
        public string $odometerReadingUnit,
        public string $image,
        public string $url,
        public Seller $seller,
        public ?int $originalPrice = null,
        public ?int $monthlyLease = null,
    ) {
    }

    /**
     * @param array{brand: string, model: string, price: int, year: int, odometerReading: int, odometerReadingUnit: string, image: string, url: string, seller: array{name: string, province: ?string, website: string, address?: ?string, city?: ?string, phone?: ?string}, originalPrice?: ?int, monthlyLease?: ?int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            brand: $data['brand'],
            model: $data['model'],
            price: $data['price'],
            year: $data['year'],
            odometerReading: $data['odometerReading'],
            odometerReadingUnit: $data['odometerReadingUnit'],
            image: $data['image'],
            url: $data['url'],
            seller: Seller::fromArray($data['seller']),
            originalPrice: $data['originalPrice'] ?? null,
            monthlyLease: $data['monthlyLease'] ?? null,
        );
    }

    /**
     * @return array{brand: string, model: string, price: int, year: int, odometerReading: int, odometerReadingUnit: string, image: string, url: string, seller: array{name: string, province: ?string, website: string, address: ?string, city: ?string, phone: ?string}, originalPrice: ?int, monthlyLease: ?int}
     */
    public function toArray(): array
    {
        return [
            'brand' => $this->brand,
            'model' => $this->model,
            'price' => $this->price,
            'year' => $this->year,
            'odometerReading' => $this->odometerReading,
            'odometerReadingUnit' => $this->odometerReadingUnit,
            'image' => $this->image,
            'url' => $this->url,
            'seller' => $this->seller->toArray(),
            'originalPrice' => $this->originalPrice,
            'monthlyLease' => $this->monthlyLease,
        ];
    }
}
