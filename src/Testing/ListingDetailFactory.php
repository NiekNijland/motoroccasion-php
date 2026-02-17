<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\LicenseCategory;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\OdometerUnit;
use NiekNijland\MotorOccasion\Data\Seller;

class ListingDetailFactory
{
    /**
     * @param string[] $images
     * @param array<string, string> $specifications
     */
    public static function make(
        string $brand = 'BMW',
        string $model = 'R 1250 GS',
        int $price = 18950,
        ?int $originalPrice = null,
        ?int $monthlyLease = null,
        int $year = 2021,
        int $odometerReading = 25000,
        OdometerUnit $odometerReadingUnit = OdometerUnit::Kilometers,
        ?string $color = 'ZWART',
        ?int $powerKw = 100,
        ?LicenseCategory $license = LicenseCategory::A,
        ?bool $warranty = true,
        array $images = [],
        ?string $description = 'A well-maintained motorcycle in excellent condition.',
        array $specifications = [],
        string $url = 'https://www.motoroccasion.nl/motor/12345/bmw-r-1250-gs',
        ?Seller $seller = null,
        ?int $id = 12345,
    ): ListingDetail {
        return new ListingDetail(
            brand: $brand,
            model: $model,
            price: $price,
            originalPrice: $originalPrice,
            monthlyLease: $monthlyLease,
            year: $year,
            odometerReading: $odometerReading,
            odometerReadingUnit: $odometerReadingUnit,
            color: $color,
            powerKw: $powerKw,
            license: $license,
            warranty: $warranty,
            images: $images !== [] ? $images : [
                'https://www.motoroccasion.nl/fotos/12345/photo1.jpg',
                'https://www.motoroccasion.nl/fotos/12345/photo2.jpg',
            ],
            description: $description,
            specifications: $specifications !== [] ? $specifications : [
                'Merk' => $brand,
                'Model' => $model,
                'Bouwjaar' => (string) $year,
            ],
            url: $url,
            seller: $seller ?? SellerFactory::makeDealer(),
            id: $id,
        );
    }
}
