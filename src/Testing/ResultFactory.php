<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\OdometerUnit;
use NiekNijland\MotorOccasion\Data\PriceType;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\Seller;

class ResultFactory
{
    /**
     * @param string[] $images
     */
    public static function make(
        string $brand = 'BMW',
        string $model = 'R 1250 GS',
        ?int $askingPrice = 18950,
        PriceType $priceType = PriceType::Asking,
        int $year = 2021,
        int $odometerReading = 25000,
        OdometerUnit $odometerReadingUnit = OdometerUnit::Kilometers,
        string $image = 'https://www.motoroccasion.nl/fotos/12345/thumb.jpg',
        string $url = 'https://www.motoroccasion.nl/motor/12345/bmw-r-1250-gs',
        ?Seller $seller = null,
        ?int $id = 12345,
        ?int $originalPrice = null,
        ?int $monthlyLease = null,
        array $images = [],
    ): Result {
        return new Result(
            brand: $brand,
            model: $model,
            askingPrice: $askingPrice,
            priceType: $priceType,
            year: $year,
            odometerReading: $odometerReading,
            odometerReadingUnit: $odometerReadingUnit,
            image: $image,
            url: $url,
            seller: $seller ?? SellerFactory::make(),
            id: $id,
            originalPrice: $originalPrice,
            monthlyLease: $monthlyLease,
            images: $images !== [] ? $images : ($image !== '' ? [$image] : []),
        );
    }

    /**
     * @return Result[]
     */
    public static function makeMany(int $count = 3): array
    {
        $listings = [
            ['brand' => 'BMW', 'model' => 'R 1250 GS', 'askingPrice' => 18950, 'year' => 2021, 'id' => 12345],
            ['brand' => 'Yamaha', 'model' => 'MT 07', 'askingPrice' => 6450, 'year' => 2019, 'id' => 23456],
            ['brand' => 'Honda', 'model' => 'CB650R', 'askingPrice' => 8200, 'year' => 2022, 'id' => 34567],
            ['brand' => 'Kawasaki', 'model' => 'Z900', 'askingPrice' => 9500, 'year' => 2020, 'id' => 45678],
            ['brand' => 'KTM', 'model' => '890 Duke', 'askingPrice' => 10200, 'year' => 2023, 'id' => 56789],
        ];

        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $data = $listings[$i % count($listings)];
            $result[] = self::make(
                brand: $data['brand'],
                model: $data['model'],
                askingPrice: $data['askingPrice'],
                year: $data['year'],
                url: 'https://www.motoroccasion.nl/motor/' . $data['id'] . '/' . strtolower($data['brand']) . '-' . strtolower(str_replace(' ', '-', $data['model'])),
                id: $data['id'],
            );
        }

        return $result;
    }
}
