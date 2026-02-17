<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion;

use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Category;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\SearchCriteria;
use NiekNijland\MotorOccasion\Data\SearchResult;
use NiekNijland\MotorOccasion\Data\Type;
use NiekNijland\MotorOccasion\Exception\MotorOccasionException;

interface MotorOccasionInterface
{
    /**
     * @return Brand[]
     *
     * @throws MotorOccasionException
     */
    public function getBrands(): array;

    /**
     * @return Type[]
     *
     * @throws MotorOccasionException
     */
    public function getTypesForBrand(Brand $brand): array;

    /**
     * @throws MotorOccasionException
     */
    public function search(SearchCriteria $criteria): SearchResult;

    /**
     * @return Category[]
     *
     * @throws MotorOccasionException
     */
    public function getCategories(): array;

    /**
     * @throws MotorOccasionException
     */
    public function getOffers(int $page = 1, int $perPage = 40): SearchResult;

    /**
     * @throws MotorOccasionException
     */
    public function getDetail(Result $result): ListingDetail;

    /**
     * Force a fresh session on the next request.
     */
    public function resetSession(): void;
}
