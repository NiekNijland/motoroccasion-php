<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Tests;

use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Category;
use NiekNijland\MotorOccasion\Data\LicenseCategory;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\OdometerUnit;
use NiekNijland\MotorOccasion\Data\PriceType;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\SearchCriteria;
use NiekNijland\MotorOccasion\Data\SearchResult;
use NiekNijland\MotorOccasion\Data\Seller;
use NiekNijland\MotorOccasion\Data\SortOrder;
use NiekNijland\MotorOccasion\Data\Type;
use NiekNijland\MotorOccasion\MotorOccasion;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests that hit the live motoroccasion.nl website.
 *
 * Run separately with: composer test-integration
 * These tests are excluded from the default test suite.
 *
 * All data is fetched once in setUpBeforeClass() and shared across tests
 * to minimize HTTP requests against the live site. Creating too many
 * fresh PHP sessions in rapid succession from the same IP can trigger
 * the site's anti-bot protection, so the setup keeps the session count low.
 *
 * @group integration
 */
class IntegrationTest extends TestCase
{
    /** @var Brand[] */
    private static array $brands = [];

    /** @var Category[] */
    private static array $categories = [];

    /** @var Type[] */
    private static array $bmwTypes = [];

    private static ?SearchResult $searchResult = null;

    private static ?SearchResult $offersResult = null;

    private static ?ListingDetail $detail = null;

    /** @var string[]|null */
    private static ?array $images = null;

    private static ?Brand $bmwBrand = null;

    private static ?SearchResult $sortedByPriceResult = null;

    private static ?SearchResult $sortedByYearDescResult = null;

    public static function setUpBeforeClass(): void
    {
        // 1. Fetch brands and categories (shared session)
        $client = new MotorOccasion;
        self::$brands = $client->getBrands();
        self::$categories = $client->getCategories();

        // Find BMW for filtered tests
        foreach (self::$brands as $brand) {
            if (strtolower($brand->name) === 'bmw') {
                self::$bmwBrand = $brand;

                break;
            }
        }

        // 2. Fetch types for BMW (needs fresh session due to side effect)
        // 3. BMW-filtered search (reliable even under rate-limiting,
        //    since setSessionParam calls always trigger result generation)
        if (self::$bmwBrand instanceof Brand) {
            $typesClient = new MotorOccasion;
            self::$bmwTypes = $typesClient->getTypesForBrand(self::$bmwBrand);
            $searchClient = new MotorOccasion;
            self::$searchResult = $searchClient->search(new SearchCriteria(brand: self::$bmwBrand, perPage: 10));
            // 4. Fetch detail for first search result (reuses searchClient session)
            if (self::$searchResult->results !== []) {
                self::$detail = $searchClient->getDetail(self::$searchResult->results[0]);
                // 4b. Fetch images for the same result (reuses searchClient session)
                self::$images = $searchClient->getImages(self::$searchResult->results[0]);
            }
        }

        // 5. Offers (separate endpoint, not affected by search rate-limiting)
        $offersClient = new MotorOccasion;
        self::$offersResult = $offersClient->getOffers(perPage: 10);

        // 6. Sorted searches (verify sort order params work against live site)
        if (self::$bmwBrand instanceof Brand) {
            $sortedPriceClient = new MotorOccasion;
            self::$sortedByPriceResult = $sortedPriceClient->search(new SearchCriteria(
                brand: self::$bmwBrand,
                sortOrder: SortOrder::PriceAscending,
                perPage: 10,
            ));

            $sortedYearClient = new MotorOccasion;
            self::$sortedByYearDescResult = $sortedYearClient->search(new SearchCriteria(
                brand: self::$bmwBrand,
                sortOrder: SortOrder::YearDescending,
                perPage: 10,
            ));
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$brands = [];
        self::$categories = [];
        self::$bmwTypes = [];
        self::$searchResult = null;
        self::$offersResult = null;
        self::$detail = null;
        self::$images = null;
        self::$bmwBrand = null;
        self::$sortedByPriceResult = null;
        self::$sortedByYearDescResult = null;
    }

    // --- Brands ---

    public function test_get_brands_returns_non_empty_list(): void
    {
        $this->assertNotEmpty(self::$brands, 'Expected at least one brand from the live site');
    }

    public function test_brands_are_brand_instances(): void
    {
        foreach (self::$brands as $brand) {
            $this->assertInstanceOf(Brand::class, $brand);
            $this->assertNotEmpty($brand->name, 'Brand name should not be empty');
            $this->assertNotEmpty($brand->value, 'Brand value should not be empty');
            $this->assertNotSame('-1', $brand->value, 'Placeholder value should be filtered');
        }
    }

    public function test_brands_contain_common_motorcycle_brands(): void
    {
        $brandNames = array_map(fn (Brand $b): string => strtolower($b->name), self::$brands);

        $this->assertContains('bmw', $brandNames, 'Expected BMW in brands list');
        $this->assertContains('honda', $brandNames, 'Expected Honda in brands list');
        $this->assertContains('yamaha', $brandNames, 'Expected Yamaha in brands list');
        $this->assertContains('kawasaki', $brandNames, 'Expected Kawasaki in brands list');
    }

    // --- Categories ---

    public function test_get_categories_returns_non_empty_list(): void
    {
        $this->assertNotEmpty(self::$categories, 'Expected at least one category from the live site');
    }

    public function test_categories_are_category_instances(): void
    {
        foreach (self::$categories as $category) {
            $this->assertInstanceOf(Category::class, $category);
            $this->assertNotEmpty($category->name, 'Category name should not be empty');
            $this->assertNotEmpty($category->value, 'Category value should not be empty');
            $this->assertNotSame('-1', $category->value, 'Placeholder value should be filtered');
        }
    }

    // --- Types for brand ---

    public function test_get_types_for_brand_returns_non_empty_list(): void
    {
        $this->assertNotNull(self::$bmwBrand, 'BMW brand not found in brands list');
        $this->assertNotEmpty(self::$bmwTypes, 'Expected at least one type for BMW');
    }

    public function test_types_are_type_instances_with_brand_reference(): void
    {
        $this->assertNotNull(self::$bmwBrand);

        foreach (self::$bmwTypes as $type) {
            $this->assertInstanceOf(Type::class, $type);
            $this->assertNotEmpty($type->name, 'Type name should not be empty');
            $this->assertNotEmpty($type->value, 'Type value should not be empty');
            $this->assertSame(self::$bmwBrand, $type->brand, 'Type should reference the requested brand');
        }
    }

    // --- Search ---

    public function test_search_returns_results(): void
    {
        $this->assertNotNull(self::$searchResult);
        $this->assertInstanceOf(SearchResult::class, self::$searchResult);
        $this->assertNotEmpty(self::$searchResult->results, 'Expected at least one result');
        $this->assertSame(1, self::$searchResult->currentPage);
        $this->assertSame(10, self::$searchResult->perPage);
    }

    public function test_search_total_count_is_estimated_from_pagination(): void
    {
        $this->assertNotNull(self::$searchResult);

        // BMW has thousands of listings; totalCount should be estimated from pagination buttons
        $this->assertGreaterThan(100, self::$searchResult->totalCount, 'Expected BMW total count > 100');
        $this->assertGreaterThan(1, self::$searchResult->totalPages(), 'Expected multiple pages of BMW results');
        $this->assertTrue(self::$searchResult->hasNextPage(), 'Expected more pages after page 1');
    }

    public function test_search_results_have_required_fields(): void
    {
        $this->assertNotNull(self::$searchResult);

        foreach (self::$searchResult->results as $result) {
            $this->assertInstanceOf(Result::class, $result);
            $this->assertNotEmpty($result->brand, 'Result brand should not be empty');
            $this->assertNotEmpty($result->url, 'Result URL should not be empty');
            $this->assertInstanceOf(OdometerUnit::class, $result->odometerReadingUnit);
            $this->assertInstanceOf(Seller::class, $result->seller);
        }
    }

    public function test_search_results_have_listing_ids(): void
    {
        $this->assertNotNull(self::$searchResult);

        foreach (self::$searchResult->results as $result) {
            $this->assertNotNull($result->id, 'Search result should have a listing ID');
            $this->assertGreaterThan(0, $result->id, 'Listing ID should be positive');
        }
    }

    public function test_search_results_match_brand_filter(): void
    {
        $this->assertNotNull(self::$searchResult);

        foreach (self::$searchResult->results as $result) {
            $this->assertSame('BMW', $result->brand, 'All results should be BMW when filtering by BMW');
        }
    }

    // --- Offers ---

    public function test_get_offers_returns_results(): void
    {
        $this->assertNotNull(self::$offersResult);
        $this->assertInstanceOf(SearchResult::class, self::$offersResult);
        $this->assertNotEmpty(self::$offersResult->results, 'Expected at least one offer');
        $this->assertSame(1, self::$offersResult->currentPage);
        $this->assertSame(10, self::$offersResult->perPage);
    }

    public function test_offers_total_count_is_estimated_from_pagination(): void
    {
        $this->assertNotNull(self::$offersResult);

        // Offers page should have many listings with pagination
        $this->assertGreaterThan(10, self::$offersResult->totalCount, 'Expected offers total count > 10');
    }

    public function test_offers_have_required_fields(): void
    {
        $this->assertNotNull(self::$offersResult);

        foreach (self::$offersResult->results as $result) {
            $this->assertInstanceOf(Result::class, $result);
            $this->assertNotEmpty($result->brand, 'Offer brand should not be empty');
            $this->assertNotEmpty($result->url, 'Offer URL should not be empty');
            $this->assertNotNull($result->askingPrice, 'Offer asking price should not be null');
            $this->assertGreaterThan(0, $result->askingPrice, 'Offer asking price should be > 0');
            $this->assertSame(PriceType::Asking, $result->priceType, 'Offer price type should be Asking');
            $this->assertInstanceOf(OdometerUnit::class, $result->odometerReadingUnit);
            $this->assertInstanceOf(Seller::class, $result->seller);
        }
    }

    public function test_offers_have_listing_ids(): void
    {
        $this->assertNotNull(self::$offersResult);

        foreach (self::$offersResult->results as $result) {
            $this->assertNotNull($result->id, 'Offer should have a listing ID');
            $this->assertGreaterThan(0, $result->id, 'Listing ID should be positive');
        }
    }

    // --- Detail ---

    public function test_get_detail_parses_live_listing(): void
    {
        $this->assertNotNull(self::$detail, 'Detail could not be fetched (no search results?)');
        $this->assertNotNull(self::$searchResult);

        $this->assertInstanceOf(ListingDetail::class, self::$detail);
        $this->assertNotEmpty(self::$detail->brand, 'Detail brand should not be empty');
        $this->assertNotEmpty(self::$detail->url, 'Detail URL should not be empty');
        $this->assertSame(
            self::$searchResult->results[0]->url,
            self::$detail->url,
            'Detail URL should match the requested result URL',
        );
        $this->assertInstanceOf(OdometerUnit::class, self::$detail->odometerReadingUnit);
        $this->assertIsArray(self::$detail->images);
        $this->assertIsArray(self::$detail->specifications);
        $this->assertInstanceOf(Seller::class, self::$detail->seller);
    }

    public function test_get_detail_has_listing_id(): void
    {
        $this->assertNotNull(self::$detail, 'Detail could not be fetched');
        $this->assertNotNull(self::$searchResult);

        $this->assertNotNull(self::$detail->id, 'Detail should have a listing ID');
        $this->assertGreaterThan(0, self::$detail->id, 'Listing ID should be positive');

        // Detail ID should match the search result ID
        $this->assertSame(
            self::$searchResult->results[0]->id,
            self::$detail->id,
            'Detail listing ID should match the search result ID',
        );
    }

    public function test_get_detail_license_is_enum_or_null(): void
    {
        $this->assertNotNull(self::$detail, 'Detail could not be fetched');

        if (self::$detail->license instanceof LicenseCategory) {
            $this->assertInstanceOf(LicenseCategory::class, self::$detail->license);
        } else {
            $this->assertNull(self::$detail->license);
        }
    }

    public function test_get_detail_has_pricing(): void
    {
        $this->assertNotNull(self::$detail, 'Detail could not be fetched');

        $this->assertNotNull(self::$detail->askingPrice, 'Detail asking price should not be null');
        $this->assertGreaterThan(0, self::$detail->askingPrice, 'Detail asking price should be > 0');
        $this->assertInstanceOf(PriceType::class, self::$detail->priceType);
        $this->assertGreaterThan(0, self::$detail->year, 'Detail year should be > 0');
    }

    // --- Images ---

    public function test_get_images_returns_high_resolution_urls(): void
    {
        $this->assertNotNull(self::$images, 'Images could not be fetched (no search results?)');
        $this->assertNotEmpty(self::$images, 'Expected at least one image from getImages()');

        foreach (self::$images as $image) {
            $this->assertIsString($image);
            $this->assertNotEmpty($image, 'Image URL should not be empty');
            $this->assertStringStartsWith('https://', $image, 'Image URL should be absolute HTTPS');
        }
    }

    public function test_get_images_matches_detail_images(): void
    {
        $this->assertNotNull(self::$images, 'Images could not be fetched');
        $this->assertNotNull(self::$detail, 'Detail could not be fetched');

        $this->assertSame(
            count(self::$detail->images),
            count(self::$images),
            'getImages() should return the same number of images as getDetail()->images',
        );

        $this->assertSame(
            self::$detail->images,
            self::$images,
            'getImages() should return identical URLs to getDetail()->images',
        );
    }

    public function test_search_results_have_images_array(): void
    {
        $this->assertNotNull(self::$searchResult);

        foreach (self::$searchResult->results as $result) {
            $this->assertIsArray($result->images, 'Result should have images array');
            $this->assertNotEmpty($result->images, 'Result images array should contain the thumbnail');
            $this->assertSame($result->image, $result->images[0], 'First image in array should match the thumbnail');
        }
    }

    // --- Sort order ---

    public function test_search_with_price_ascending_sort_returns_results(): void
    {
        $this->assertNotNull(self::$sortedByPriceResult, 'Sorted search result was not fetched');
        $this->assertInstanceOf(SearchResult::class, self::$sortedByPriceResult);
        $this->assertNotEmpty(self::$sortedByPriceResult->results, 'Expected at least one result for price-sorted search');
    }

    public function test_search_with_price_ascending_sort_returns_ascending_prices(): void
    {
        $this->assertNotNull(self::$sortedByPriceResult);
        $this->assertCount(10, self::$sortedByPriceResult->results, 'Expected 10 results');

        $prices = array_values(array_filter(
            array_map(fn (Result $r): ?int => $r->askingPrice, self::$sortedByPriceResult->results),
            fn (?int $p): bool => $p !== null,
        ));
        $counter = count($prices);

        $this->assertGreaterThan(0, $counter, 'Expected at least one result with a non-null price');

        for ($i = 1; $i < $counter; $i++) {
            $this->assertGreaterThanOrEqual(
                $prices[$i - 1],
                $prices[$i],
                sprintf('Price at index %d (%d) should be >= price at index %d (%d) for ascending sort', $i, $prices[$i], $i - 1, $prices[$i - 1]),
            );
        }
    }

    public function test_search_with_year_descending_sort_returns_results(): void
    {
        $this->assertNotNull(self::$sortedByYearDescResult, 'Sorted search result was not fetched');
        $this->assertInstanceOf(SearchResult::class, self::$sortedByYearDescResult);
        $this->assertNotEmpty(self::$sortedByYearDescResult->results, 'Expected at least one result for year-sorted search');
    }

    public function test_search_with_year_descending_sort_returns_descending_years(): void
    {
        $this->assertNotNull(self::$sortedByYearDescResult);
        $this->assertCount(10, self::$sortedByYearDescResult->results, 'Expected 10 results');

        $years = array_map(fn (Result $r): int => $r->year, self::$sortedByYearDescResult->results);
        $counter = count($years);

        for ($i = 1; $i < $counter; $i++) {
            $this->assertLessThanOrEqual(
                $years[$i - 1],
                $years[$i],
                sprintf('Year at index %d (%d) should be <= year at index %d (%d) for descending sort', $i, $years[$i], $i - 1, $years[$i - 1]),
            );
        }
    }

    // --- DTO serialization round-trip with live data ---

    public function test_result_dto_round_trip_with_live_data(): void
    {
        $this->assertNotNull(self::$searchResult);

        foreach (self::$searchResult->results as $result) {
            $array = $result->toArray();
            $reconstructed = Result::fromArray($array);

            $this->assertSame($result->brand, $reconstructed->brand);
            $this->assertSame($result->model, $reconstructed->model);
            $this->assertSame($result->askingPrice, $reconstructed->askingPrice);
            $this->assertSame($result->priceType, $reconstructed->priceType);
            $this->assertSame($result->year, $reconstructed->year);
            $this->assertSame($result->url, $reconstructed->url);
            $this->assertSame($result->seller->name, $reconstructed->seller->name);
        }
    }

    public function test_listing_detail_dto_round_trip_with_live_data(): void
    {
        $this->assertNotNull(self::$detail, 'Detail could not be fetched');

        $array = self::$detail->toArray();
        $reconstructed = ListingDetail::fromArray($array);

        $this->assertSame(self::$detail->brand, $reconstructed->brand);
        $this->assertSame(self::$detail->model, $reconstructed->model);
        $this->assertSame(self::$detail->askingPrice, $reconstructed->askingPrice);
        $this->assertSame(self::$detail->priceType, $reconstructed->priceType);
        $this->assertSame(self::$detail->url, $reconstructed->url);
        $this->assertSame(count(self::$detail->images), count($reconstructed->images));
        $this->assertSame(self::$detail->seller->name, $reconstructed->seller->name);
    }
}
