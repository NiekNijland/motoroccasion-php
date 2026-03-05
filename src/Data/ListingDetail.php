<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

use NiekNijland\MotorOccasion\Exception\MotorOccasionException;
use ValueError;

readonly class ListingDetail
{
    /**
     * @param string[] $images
     * @param array<string, string> $specifications
     */
    public function __construct(
        public string $brand,
        public string $model,
        public ?int $askingPrice,
        public PriceType $priceType,
        public ?int $originalPrice,
        public ?int $monthlyLease,
        public int $year,
        public int $odometerReading,
        public OdometerUnit $odometerReadingUnit,
        public ?string $color,
        public ?int $powerKw,
        public ?LicenseCategory $license,
        public ?bool $warranty,
        public array $images,
        public ?string $description,
        public array $specifications,
        public string $url,
        public Seller $seller,
        public Engine $engine = new Engine(),
        public Chassis $chassis = new Chassis(),
        public Dimensions $dimensions = new Dimensions(),
        public ?int $id = null,
        public ?bool $vatDeductible = null,
        public ?string $licensePlate = null,
        public ?string $damageStatus = null,
        public ?string $bodyType = null,
        public ?string $roadTaxStatus = null,
        public ?string $availableColors = null,
        public ?bool $isNew = null,
        public ?int $modelYear = null,
        public ?int $factoryWarrantyMonths = null,
        public ?string $dealerDescription = null,
    ) {
    }

    /**
     * @param array{brand: string, model: string, askingPrice: ?int, priceType: string, originalPrice: ?int, monthlyLease: ?int, year: int, odometerReading: int, odometerReadingUnit: string, color: ?string, powerKw: ?int, license: ?string, warranty: ?bool, images: string[], description: ?string, specifications: array<string, string>, url: string, seller: array{name: string, province: ?string, website: string, address?: ?string, city?: ?string, phone?: ?string, postalCode?: ?string}, engine?: array{capacityCc?: ?int, type?: ?string, cylinders?: ?int, valves?: ?int, boreAndStroke?: ?string, compressionRatio?: ?string, fuelDelivery?: ?string, fuelType?: ?string, isElectric?: ?bool, ignition?: ?string, maxTorque?: ?string, clutch?: ?string, gearbox?: ?string, driveType?: ?string, starter?: ?string, topSpeed?: ?string}, chassis?: array{abs?: ?bool, frameType?: ?string, frontSuspension?: ?string, rearSuspension?: ?string, frontBrake?: ?string, rearBrake?: ?string, frontTire?: ?string, rearTire?: ?string}, dimensions?: array{seatHeightMm?: ?int, wheelbaseMm?: ?int, lengthMm?: ?int, widthMm?: ?int, heightMm?: ?int, tankCapacityLiters?: ?float, weightKg?: ?int}, id?: ?int, vatDeductible?: ?bool, licensePlate?: ?string, damageStatus?: ?string, bodyType?: ?string, roadTaxStatus?: ?string, availableColors?: ?string, isNew?: ?bool, modelYear?: ?int, factoryWarrantyMonths?: ?int, dealerDescription?: ?string} $data
     *
     * @throws MotorOccasionException
     */
    public static function fromArray(array $data): self
    {
        try {
            $odometerUnit = OdometerUnit::from($data['odometerReadingUnit']);
        } catch (ValueError $valueError) {
            throw new MotorOccasionException('Invalid odometer unit: ' . $data['odometerReadingUnit'], $valueError->getCode(), previous: $valueError);
        }

        try {
            $priceType = PriceType::from($data['priceType']);
        } catch (ValueError $valueError) {
            throw new MotorOccasionException('Invalid price type: ' . $data['priceType'], $valueError->getCode(), previous: $valueError);
        }

        $license = null;
        if (isset($data['license'])) {
            try {
                $license = LicenseCategory::from($data['license']);
            } catch (ValueError $valueError) {
                throw new MotorOccasionException('Invalid license category: ' . $data['license'], $valueError->getCode(), previous: $valueError);
            }
        }

        return new self(
            brand: $data['brand'],
            model: $data['model'],
            askingPrice: $data['askingPrice'],
            priceType: $priceType,
            originalPrice: $data['originalPrice'],
            monthlyLease: $data['monthlyLease'],
            year: $data['year'],
            odometerReading: $data['odometerReading'],
            odometerReadingUnit: $odometerUnit,
            color: $data['color'],
            powerKw: $data['powerKw'],
            license: $license,
            warranty: $data['warranty'],
            images: $data['images'],
            description: $data['description'],
            specifications: $data['specifications'],
            url: $data['url'],
            seller: Seller::fromArray($data['seller']),
            engine: Engine::fromArray($data['engine'] ?? []),
            chassis: Chassis::fromArray($data['chassis'] ?? []),
            dimensions: Dimensions::fromArray($data['dimensions'] ?? []),
            id: $data['id'] ?? null,
            vatDeductible: $data['vatDeductible'] ?? null,
            licensePlate: $data['licensePlate'] ?? null,
            damageStatus: $data['damageStatus'] ?? null,
            bodyType: $data['bodyType'] ?? null,
            roadTaxStatus: $data['roadTaxStatus'] ?? null,
            availableColors: $data['availableColors'] ?? null,
            isNew: $data['isNew'] ?? null,
            modelYear: $data['modelYear'] ?? null,
            factoryWarrantyMonths: $data['factoryWarrantyMonths'] ?? null,
            dealerDescription: $data['dealerDescription'] ?? null,
        );
    }

    /**
     * @return array{brand: string, model: string, askingPrice: ?int, priceType: string, originalPrice: ?int, monthlyLease: ?int, year: int, odometerReading: int, odometerReadingUnit: string, color: ?string, powerKw: ?int, license: ?string, warranty: ?bool, images: string[], description: ?string, specifications: array<string, string>, url: string, seller: array{name: string, province: ?string, website: string, address: ?string, city: ?string, phone: ?string, postalCode: ?string}, engine: array{capacityCc: ?int, type: ?string, cylinders: ?int, valves: ?int, boreAndStroke: ?string, compressionRatio: ?string, fuelDelivery: ?string, fuelType: ?string, isElectric: ?bool, ignition: ?string, maxTorque: ?string, clutch: ?string, gearbox: ?string, driveType: ?string, starter: ?string, topSpeed: ?string}, chassis: array{abs: ?bool, frameType: ?string, frontSuspension: ?string, rearSuspension: ?string, frontBrake: ?string, rearBrake: ?string, frontTire: ?string, rearTire: ?string}, dimensions: array{seatHeightMm: ?int, wheelbaseMm: ?int, lengthMm: ?int, widthMm: ?int, heightMm: ?int, tankCapacityLiters: ?float, weightKg: ?int}, id: ?int, vatDeductible: ?bool, licensePlate: ?string, damageStatus: ?string, bodyType: ?string, roadTaxStatus: ?string, availableColors: ?string, isNew: ?bool, modelYear: ?int, factoryWarrantyMonths: ?int, dealerDescription: ?string}
     */
    public function toArray(): array
    {
        return [
            'brand' => $this->brand,
            'model' => $this->model,
            'askingPrice' => $this->askingPrice,
            'priceType' => $this->priceType->value,
            'originalPrice' => $this->originalPrice,
            'monthlyLease' => $this->monthlyLease,
            'year' => $this->year,
            'odometerReading' => $this->odometerReading,
            'odometerReadingUnit' => $this->odometerReadingUnit->value,
            'color' => $this->color,
            'powerKw' => $this->powerKw,
            'license' => $this->license?->value,
            'warranty' => $this->warranty,
            'images' => $this->images,
            'description' => $this->description,
            'specifications' => $this->specifications,
            'url' => $this->url,
            'seller' => $this->seller->toArray(),
            'engine' => $this->engine->toArray(),
            'chassis' => $this->chassis->toArray(),
            'dimensions' => $this->dimensions->toArray(),
            'id' => $this->id,
            'vatDeductible' => $this->vatDeductible,
            'licensePlate' => $this->licensePlate,
            'damageStatus' => $this->damageStatus,
            'bodyType' => $this->bodyType,
            'roadTaxStatus' => $this->roadTaxStatus,
            'availableColors' => $this->availableColors,
            'isNew' => $this->isNew,
            'modelYear' => $this->modelYear,
            'factoryWarrantyMonths' => $this->factoryWarrantyMonths,
            'dealerDescription' => $this->dealerDescription,
        ];
    }
}
