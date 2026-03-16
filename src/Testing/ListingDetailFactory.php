<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\Chassis;
use NiekNijland\MotorOccasion\Data\Dimensions;
use NiekNijland\MotorOccasion\Data\Engine;
use NiekNijland\MotorOccasion\Data\LicenseCategory;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\OdometerUnit;
use NiekNijland\MotorOccasion\Data\PriceType;
use NiekNijland\MotorOccasion\Data\Seller;

class ListingDetailFactory
{
    /**
     * @param  string[]  $images
     * @param  array<string, string>  $specifications
     */
    public static function make(
        string $brand = 'BMW',
        string $model = 'R 1250 GS',
        ?int $askingPrice = 18950,
        PriceType $priceType = PriceType::Asking,
        ?int $originalPrice = null,
        ?int $monthlyLease = null,
        int $year = 2021,
        ?int $odometerReading = 25000,
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
        ?Engine $engine = null,
        ?Chassis $chassis = null,
        ?Dimensions $dimensions = null,
        ?int $id = 12345,
        ?bool $vatDeductible = null,
        ?string $licensePlate = null,
        ?string $damageStatus = null,
        ?string $bodyType = null,
        ?string $roadTaxStatus = null,
        ?string $availableColors = null,
        ?bool $isNew = null,
        ?int $modelYear = null,
        ?int $factoryWarrantyMonths = null,
        ?string $dealerDescription = null,
    ): ListingDetail {
        return new ListingDetail(
            brand: $brand,
            model: $model,
            askingPrice: $askingPrice,
            priceType: $priceType,
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
            engine: $engine ?? new Engine,
            chassis: $chassis ?? new Chassis,
            dimensions: $dimensions ?? new Dimensions,
            id: $id,
            vatDeductible: $vatDeductible,
            licensePlate: $licensePlate,
            damageStatus: $damageStatus,
            bodyType: $bodyType,
            roadTaxStatus: $roadTaxStatus,
            availableColors: $availableColors,
            isNew: $isNew,
            modelYear: $modelYear,
            factoryWarrantyMonths: $factoryWarrantyMonths,
            dealerDescription: $dealerDescription,
        );
    }
}
