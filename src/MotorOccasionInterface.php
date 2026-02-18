<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion;

use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Category;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\SearchCriteria;
use NiekNijland\MotorOccasion\Data\SearchResult;
use NiekNijland\MotorOccasion\Data\SortOrder;
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
    public function getOffers(int $page = 1, int $perPage = 40, ?SortOrder $sortOrder = null): SearchResult;

    /**
     * @throws MotorOccasionException
     */
    public function getDetail(Result $result): ListingDetail;

    /**
     * Fetch high-resolution image URLs for a listing.
     *
     * Loads the detail page and extracts all gallery images at the highest
     * available resolution (typically 1024x768). This is useful when you
     * need high-quality images from search results without fetching the
     * full listing detail.
     *
     * @return string[]
     *
     * @throws MotorOccasionException
     */
    public function getImages(Result $result): array;

    /**
     * Force a fresh session on the next request.
     */
    public function resetSession(): void;
}
