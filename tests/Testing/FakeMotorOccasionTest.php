<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Tests\Testing;

use NiekNijland\MotorOccasion\Data\SearchCriteria;
use NiekNijland\MotorOccasion\Data\SearchResult;
use NiekNijland\MotorOccasion\Exception\MotorOccasionException;
use NiekNijland\MotorOccasion\MotorOccasionInterface;
use NiekNijland\MotorOccasion\Testing\BrandFactory;
use NiekNijland\MotorOccasion\Testing\CategoryFactory;
use NiekNijland\MotorOccasion\Testing\FakeMotorOccasion;
use NiekNijland\MotorOccasion\Testing\ListingDetailFactory;
use NiekNijland\MotorOccasion\Testing\ResultFactory;
use NiekNijland\MotorOccasion\Testing\TypeFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FakeMotorOccasionTest extends TestCase
{
    public function test_implements_interface(): void
    {
        $fake = new FakeMotorOccasion();

        $this->assertInstanceOf(MotorOccasionInterface::class, $fake);
    }

    // --- Brands ---

    public function test_returns_seeded_brands(): void
    {
        $brands = BrandFactory::makeMany(3);
        $fake = new FakeMotorOccasion(brands: $brands);

        $this->assertSame($brands, $fake->getBrands());
    }

    public function test_returns_empty_brands_by_default(): void
    {
        $fake = new FakeMotorOccasion();

        $this->assertSame([], $fake->getBrands());
    }

    public function test_set_brands_via_setter(): void
    {
        $brands = BrandFactory::makeMany(2);
        $fake = new FakeMotorOccasion();
        $fake->setBrands($brands);

        $this->assertSame($brands, $fake->getBrands());
    }

    // --- Categories ---

    public function test_returns_seeded_categories(): void
    {
        $categories = CategoryFactory::makeMany(3);
        $fake = new FakeMotorOccasion(categories: $categories);

        $this->assertSame($categories, $fake->getCategories());
    }

    public function test_set_categories_via_setter(): void
    {
        $categories = CategoryFactory::makeMany(2);
        $fake = new FakeMotorOccasion();
        $fake->setCategories($categories);

        $this->assertSame($categories, $fake->getCategories());
    }

    // --- Types ---

    public function test_returns_seeded_types_for_brand(): void
    {
        $brand = BrandFactory::make();
        $types = TypeFactory::makeMany(3, brand: $brand);

        $fake = new FakeMotorOccasion();
        $fake->setTypesForBrand($brand, $types);

        $this->assertSame($types, $fake->getTypesForBrand($brand));
    }

    public function test_returns_empty_types_for_unknown_brand(): void
    {
        $fake = new FakeMotorOccasion();

        $this->assertSame([], $fake->getTypesForBrand(BrandFactory::make()));
    }

    // --- Search ---

    public function test_search_returns_paginated_results(): void
    {
        $results = ResultFactory::makeMany(5);
        $fake = new FakeMotorOccasion(results: $results);

        $searchResult = $fake->search(new SearchCriteria(perPage: 2));

        $this->assertInstanceOf(SearchResult::class, $searchResult);
        $this->assertCount(2, $searchResult->results);
        $this->assertSame(5, $searchResult->totalCount);
        $this->assertSame(1, $searchResult->currentPage);
        $this->assertSame(2, $searchResult->perPage);
        $this->assertTrue($searchResult->hasNextPage());
    }

    public function test_search_page_2(): void
    {
        $results = ResultFactory::makeMany(5);
        $fake = new FakeMotorOccasion(results: $results);

        $searchResult = $fake->search(new SearchCriteria(page: 2, perPage: 2));

        $this->assertCount(2, $searchResult->results);
        $this->assertSame(2, $searchResult->currentPage);
        // Results[2] and Results[3] should be the page 2 items
        $this->assertSame($results[2]->brand, $searchResult->results[0]->brand);
        $this->assertSame($results[3]->brand, $searchResult->results[1]->brand);
    }

    public function test_search_returns_empty_when_no_results(): void
    {
        $fake = new FakeMotorOccasion();

        $searchResult = $fake->search(new SearchCriteria());

        $this->assertCount(0, $searchResult->results);
        $this->assertSame(0, $searchResult->totalCount);
    }

    // --- Offers ---

    public function test_get_offers_returns_paginated_results(): void
    {
        $results = ResultFactory::makeMany(3);
        $fake = new FakeMotorOccasion(results: $results);

        $offersResult = $fake->getOffers(page: 1, perPage: 2);

        $this->assertCount(2, $offersResult->results);
        $this->assertSame(3, $offersResult->totalCount);
        $this->assertSame(1, $offersResult->currentPage);
        $this->assertSame(2, $offersResult->perPage);
    }

    // --- Detail ---

    public function test_get_detail_returns_seeded_detail(): void
    {
        $result = ResultFactory::make();
        $detail = ListingDetailFactory::make(
            brand: $result->brand,
            model: $result->model,
            url: $result->url,
        );

        $fake = new FakeMotorOccasion();
        $fake->setDetail($result, $detail);

        $this->assertSame($detail, $fake->getDetail($result));
    }

    public function test_get_detail_throws_for_unknown_result(): void
    {
        $fake = new FakeMotorOccasion();

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('No fake detail configured');

        $fake->getDetail(ResultFactory::make());
    }

    // --- Exception throwing ---

    public function test_should_throw_on_all_data_methods(): void
    {
        $exception = new MotorOccasionException('Simulated failure');
        $fake = new FakeMotorOccasion(brands: BrandFactory::makeMany(3));
        $fake->shouldThrow($exception);

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('Simulated failure');

        $fake->getBrands();
    }

    public function test_should_throw_on_search(): void
    {
        $fake = new FakeMotorOccasion();
        $fake->shouldThrow(new MotorOccasionException('Search failed'));

        $this->expectException(MotorOccasionException::class);

        $fake->search(new SearchCriteria());
    }

    public function test_reset_session_does_not_throw(): void
    {
        $fake = new FakeMotorOccasion();
        $fake->shouldThrow(new MotorOccasionException('Should not affect resetSession'));

        // resetSession should not throw even when shouldThrow is configured
        $fake->resetSession();

        $this->assertTrue($fake->wasCalled('resetSession'));
    }

    // --- Call tracking ---

    public function test_tracks_calls(): void
    {
        $fake = new FakeMotorOccasion();

        $fake->getBrands();
        $fake->getCategories();
        $fake->getBrands();

        $this->assertSame(3, $fake->callCount());
        $this->assertSame(2, $fake->callCount('getBrands'));
        $this->assertSame(1, $fake->callCount('getCategories'));
        $this->assertSame(0, $fake->callCount('search'));
    }

    public function test_was_called_and_was_not_called(): void
    {
        $fake = new FakeMotorOccasion();

        $fake->getBrands();

        $this->assertTrue($fake->wasCalled('getBrands'));
        $this->assertFalse($fake->wasCalled('search'));
        $this->assertTrue($fake->wasNotCalled('search'));
        $this->assertFalse($fake->wasNotCalled('getBrands'));
    }

    public function test_assert_called_times_passes(): void
    {
        $fake = new FakeMotorOccasion();

        $fake->getBrands();
        $fake->getBrands();

        // Should not throw
        $fake->assertCalledTimes('getBrands', 2);
        $fake->assertCalledTimes('search', 0);

        $this->assertTrue(true); // Reached without exception
    }

    public function test_assert_called_times_throws_on_mismatch(): void
    {
        $fake = new FakeMotorOccasion();
        $fake->getBrands();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected getBrands() to be called 2 time(s), but was called 1 time(s)');

        $fake->assertCalledTimes('getBrands', 2);
    }

    public function test_assert_called_passes(): void
    {
        $fake = new FakeMotorOccasion();
        $fake->getCategories();

        // Should not throw
        $fake->assertCalled('getCategories');

        $this->assertTrue(true);
    }

    public function test_assert_called_throws_when_not_called(): void
    {
        $fake = new FakeMotorOccasion();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected search() to be called, but it was never called');

        $fake->assertCalled('search');
    }

    public function test_assert_not_called_passes(): void
    {
        $fake = new FakeMotorOccasion();

        $fake->assertNotCalled('getBrands');

        $this->assertTrue(true);
    }

    public function test_assert_not_called_throws_when_called(): void
    {
        $fake = new FakeMotorOccasion();
        $fake->getBrands();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected getBrands() to never be called, but it was called 1 time(s)');

        $fake->assertNotCalled('getBrands');
    }

    public function test_get_calls_returns_recorded_args(): void
    {
        $brand = BrandFactory::make();
        $fake = new FakeMotorOccasion();

        $fake->getTypesForBrand($brand);

        $calls = $fake->getCalls('getTypesForBrand');

        $this->assertCount(1, $calls);
        $this->assertSame($brand, $calls[0]['args']['brand']);
    }

    public function test_reset_calls_clears_history(): void
    {
        $fake = new FakeMotorOccasion();

        $fake->getBrands();
        $fake->getCategories();

        $this->assertSame(2, $fake->callCount());

        $fake->resetCalls();

        $this->assertSame(0, $fake->callCount());
    }

    // --- Fluent setters ---

    public function test_setters_return_self_for_chaining(): void
    {
        $fake = new FakeMotorOccasion();

        $result = $fake
            ->setBrands(BrandFactory::makeMany(2))
            ->setCategories(CategoryFactory::makeMany(2))
            ->setResults(ResultFactory::makeMany(3));

        $this->assertSame($fake, $result);
        $this->assertCount(2, $fake->getBrands());
        $this->assertCount(2, $fake->getCategories());

        $searchResult = $fake->search(new SearchCriteria());
        $this->assertSame(3, $searchResult->totalCount);
    }

    // --- Images ---

    public function test_get_images_returns_seeded_images(): void
    {
        $result = ResultFactory::make();
        $images = [
            'https://www.motoroccasion.nl/fotos/12345/photo1.jpg',
            'https://www.motoroccasion.nl/fotos/12345/photo2.jpg',
        ];

        $fake = new FakeMotorOccasion();
        $fake->setImages($result, $images);

        $this->assertSame($images, $fake->getImages($result));
    }

    public function test_get_images_falls_back_to_detail_images(): void
    {
        $result = ResultFactory::make();
        $detail = ListingDetailFactory::make(url: $result->url);

        $fake = new FakeMotorOccasion();
        $fake->setDetail($result, $detail);

        $this->assertSame($detail->images, $fake->getImages($result));
    }

    public function test_get_images_prefers_direct_images_over_detail(): void
    {
        $result = ResultFactory::make();
        $detail = ListingDetailFactory::make(url: $result->url);
        $directImages = ['https://example.com/direct.jpg'];

        $fake = new FakeMotorOccasion();
        $fake->setDetail($result, $detail);
        $fake->setImages($result, $directImages);

        $this->assertSame($directImages, $fake->getImages($result));
    }

    public function test_get_images_throws_when_no_images_or_detail_configured(): void
    {
        $fake = new FakeMotorOccasion();

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('No fake images or detail configured');

        $fake->getImages(ResultFactory::make());
    }

    public function test_set_images_returns_self_for_chaining(): void
    {
        $fake = new FakeMotorOccasion();

        $result = $fake->setImages(ResultFactory::make(), ['https://example.com/photo.jpg']);

        $this->assertSame($fake, $result);
    }
}
