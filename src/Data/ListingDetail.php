<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class ListingDetail
{
    /**
     * @param string[] $images
     * @param array<string, string> $specifications
     */
    public function __construct(
        public string $brand,
        public string $model,
        public int $price,
        public ?int $originalPrice,
        public ?int $monthlyLease,
        public int $year,
        public int $odometerReading,
        public string $odometerReadingUnit,
        public ?string $color,
        public ?int $powerKw,
        public ?string $license,
        public ?bool $warranty,
        public array $images,
        public ?string $description,
        public array $specifications,
        public string $url,
        public Seller $seller,
    ) {
    }

    /**
     * @param array{brand: string, model: string, price: int, originalPrice: ?int, monthlyLease: ?int, year: int, odometerReading: int, odometerReadingUnit: string, color: ?string, powerKw: ?int, license: ?string, warranty: ?bool, images: string[], description: ?string, specifications: array<string, string>, url: string, seller: array{name: string, province: ?string, website: string, address: ?string, city: ?string, phone: ?string}} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            brand: $data['brand'],
            model: $data['model'],
            price: $data['price'],
            originalPrice: $data['originalPrice'],
            monthlyLease: $data['monthlyLease'],
            year: $data['year'],
            odometerReading: $data['odometerReading'],
            odometerReadingUnit: $data['odometerReadingUnit'],
            color: $data['color'],
            powerKw: $data['powerKw'],
            license: $data['license'],
            warranty: $data['warranty'],
            images: $data['images'],
            description: $data['description'],
            specifications: $data['specifications'],
            url: $data['url'],
            seller: Seller::fromArray($data['seller']),
        );
    }

    /**
     * @return array{brand: string, model: string, price: int, originalPrice: ?int, monthlyLease: ?int, year: int, odometerReading: int, odometerReadingUnit: string, color: ?string, powerKw: ?int, license: ?string, warranty: ?bool, images: string[], description: ?string, specifications: array<string, string>, url: string, seller: array{name: string, province: ?string, website: string, address: ?string, city: ?string, phone: ?string}}
     */
    public function toArray(): array
    {
        return [
            'brand' => $this->brand,
            'model' => $this->model,
            'price' => $this->price,
            'originalPrice' => $this->originalPrice,
            'monthlyLease' => $this->monthlyLease,
            'year' => $this->year,
            'odometerReading' => $this->odometerReading,
            'odometerReadingUnit' => $this->odometerReadingUnit,
            'color' => $this->color,
            'powerKw' => $this->powerKw,
            'license' => $this->license,
            'warranty' => $this->warranty,
            'images' => $this->images,
            'description' => $this->description,
            'specifications' => $this->specifications,
            'url' => $this->url,
            'seller' => $this->seller->toArray(),
        ];
    }
}
