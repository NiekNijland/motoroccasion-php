<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\OdometerUnit;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\Seller;

class ResultFactory
{
    public static function make(
        string $brand = 'BMW',
        string $model = 'R 1250 GS',
        int $price = 18950,
        int $year = 2021,
        int $odometerReading = 25000,
        OdometerUnit $odometerReadingUnit = OdometerUnit::Kilometers,
        string $image = 'https://www.motoroccasion.nl/fotos/12345/thumb.jpg',
        string $url = '/motor/12345/bmw-r-1250-gs',
        ?Seller $seller = null,
        ?int $id = 12345,
        ?int $originalPrice = null,
        ?int $monthlyLease = null,
    ): Result {
        return new Result(
            brand: $brand,
            model: $model,
            price: $price,
            year: $year,
            odometerReading: $odometerReading,
            odometerReadingUnit: $odometerReadingUnit,
            image: $image,
            url: $url,
            seller: $seller ?? SellerFactory::make(),
            id: $id,
            originalPrice: $originalPrice,
            monthlyLease: $monthlyLease,
        );
    }

    /**
     * @return Result[]
     */
    public static function makeMany(int $count = 3): array
    {
        $listings = [
            ['brand' => 'BMW', 'model' => 'R 1250 GS', 'price' => 18950, 'year' => 2021, 'id' => 12345],
            ['brand' => 'Yamaha', 'model' => 'MT 07', 'price' => 6450, 'year' => 2019, 'id' => 23456],
            ['brand' => 'Honda', 'model' => 'CB650R', 'price' => 8200, 'year' => 2022, 'id' => 34567],
            ['brand' => 'Kawasaki', 'model' => 'Z900', 'price' => 9500, 'year' => 2020, 'id' => 45678],
            ['brand' => 'KTM', 'model' => '890 Duke', 'price' => 10200, 'year' => 2023, 'id' => 56789],
        ];

        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $data = $listings[$i % count($listings)];
            $result[] = self::make(
                brand: $data['brand'],
                model: $data['model'],
                price: $data['price'],
                year: $data['year'],
                url: '/motor/' . $data['id'] . '/' . strtolower($data['brand']) . '-' . strtolower(str_replace(' ', '-', $data['model'])),
                id: $data['id'],
            );
        }

        return $result;
    }
}
