<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class SearchCriteria
{
    public function __construct(
        public ?Brand $brand = null,
        public ?Type $type = null,
        public ?Category $category = null,
        public ?int $priceMin = null,
        public ?int $priceMax = null,
        public ?int $yearMin = null,
        public ?int $yearMax = null,
        public ?int $odometerMin = null,
        public ?int $odometerMax = null,
        public ?int $engineCapacityMin = null,
        public ?int $engineCapacityMax = null,
        public ?int $powerMin = null,
        public ?int $powerMax = null,
        public ?LicenseCategory $license = null,
        public ?bool $electric = null,
        public ?bool $vatDeductible = null,
        public ?string $postalCode = null,
        public ?int $radius = null,
        public ?string $keywords = null,
        public ?string $selection = null,
        public ?SortOrder $sortOrder = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {
    }
}
