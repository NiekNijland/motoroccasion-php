<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

use NiekNijland\MotorOccasion\Exception\MotorOccasionException;
use ValueError;

readonly class Result
{
    /**
     * @param  string[]  $images
     */
    public function __construct(
        public string $brand,
        public string $model,
        public ?int $askingPrice,
        public PriceType $priceType,
        public int $year,
        public ?int $odometerReading,
        public OdometerUnit $odometerReadingUnit,
        public string $image,
        public string $url,
        public Seller $seller,
        public ?int $id = null,
        public ?int $originalPrice = null,
        public ?int $monthlyLease = null,
        public array $images = [],
    ) {}

    /**
     * @param  array{brand: string, model: string, askingPrice: ?int, priceType: string, year: int, odometerReading: ?int, odometerReadingUnit: string, image: string, url: string, seller: array{name: string, province: ?string, website: string, address?: ?string, city?: ?string, phone?: ?string}, id?: ?int, originalPrice?: ?int, monthlyLease?: ?int, images?: string[]}  $data
     *
     * @throws MotorOccasionException
     */
    public static function fromArray(array $data): self
    {
        try {
            $odometerUnit = OdometerUnit::from($data['odometerReadingUnit']);
        } catch (ValueError $valueError) {
            throw new MotorOccasionException('Invalid odometer unit: '.$data['odometerReadingUnit'], $valueError->getCode(), previous: $valueError);
        }

        try {
            $priceType = PriceType::from($data['priceType']);
        } catch (ValueError $valueError) {
            throw new MotorOccasionException('Invalid price type: '.$data['priceType'], $valueError->getCode(), previous: $valueError);
        }

        return new self(
            brand: $data['brand'],
            model: $data['model'],
            askingPrice: $data['askingPrice'],
            priceType: $priceType,
            year: $data['year'],
            odometerReading: $data['odometerReading'],
            odometerReadingUnit: $odometerUnit,
            image: $data['image'],
            url: $data['url'],
            seller: Seller::fromArray($data['seller']),
            id: $data['id'] ?? null,
            originalPrice: $data['originalPrice'] ?? null,
            monthlyLease: $data['monthlyLease'] ?? null,
            images: $data['images'] ?? [],
        );
    }

    /**
     * @return array{brand: string, model: string, askingPrice: ?int, priceType: string, year: int, odometerReading: ?int, odometerReadingUnit: string, image: string, url: string, seller: array{name: string, province: ?string, website: string, address: ?string, city: ?string, phone: ?string}, id: ?int, originalPrice: ?int, monthlyLease: ?int, images: string[]}
     */
    public function toArray(): array
    {
        return [
            'brand' => $this->brand,
            'model' => $this->model,
            'askingPrice' => $this->askingPrice,
            'priceType' => $this->priceType->value,
            'year' => $this->year,
            'odometerReading' => $this->odometerReading,
            'odometerReadingUnit' => $this->odometerReadingUnit->value,
            'image' => $this->image,
            'url' => $this->url,
            'seller' => $this->seller->toArray(),
            'id' => $this->id,
            'originalPrice' => $this->originalPrice,
            'monthlyLease' => $this->monthlyLease,
            'images' => $this->images,
        ];
    }
}
