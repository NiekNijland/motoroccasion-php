<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Category;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\Province;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\SearchCriteria;
use NiekNijland\MotorOccasion\Data\SearchResult;
use NiekNijland\MotorOccasion\Data\Seller;
use NiekNijland\MotorOccasion\Data\Type;
use NiekNijland\MotorOccasion\MotorOccasion;
use PHPUnit\Framework\TestCase;

class MotorOccasionTest extends TestCase
{
    private function createClientWithMock(MockHandler $mockHandler): Client
    {
        return new Client([
            'handler' => HandlerStack::create($mockHandler),
        ]);
    }

    private function sessionResponse(): Response
    {
        return new Response(200, ['Set-Cookie' => 'PHPSESSID=abc123; path=/'], $this->homepageFixture());
    }

    private function okResponse(string $body = ''): Response
    {
        return new Response(200, [], $body);
    }

    private function homepageFixture(): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/homepage.html');
    }

    private function searchFormFixture(): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/search-form.json');
    }

    private function resultsFixture(): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/results.html');
    }

    private function detailFixture(): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/detail.html');
    }

    private function offersFixture(): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/offers.html');
    }

    public function test_get_brands_parses_brand_options(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->searchFormFixture()),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $brands = $client->getBrands();

        $this->assertCount(3, $brands);

        $this->assertSame('BMW', $brands[0]->name);
        $this->assertSame('bmw', $brands[0]->value);

        $this->assertSame('Honda', $brands[1]->name);
        $this->assertSame('honda', $brands[1]->value);

        $this->assertSame('Yamaha', $brands[2]->name);
        $this->assertSame('yamaha', $brands[2]->value);
    }

    public function test_get_brands_skips_placeholder_option(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->searchFormFixture()),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $brands = $client->getBrands();

        foreach ($brands as $brand) {
            $this->assertNotSame('-1', $brand->value);
            $this->assertNotSame('Kies een merk', $brand->name);
        }
    }

    public function test_get_types_for_brand_parses_type_options(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setBrand response
            $this->okResponse($this->searchFormFixture()),
        ]);

        $brand = new Brand(name: 'BMW', value: 'bmw');

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $types = $client->getTypesForBrand($brand);

        $this->assertCount(2, $types);

        $this->assertSame('R 1250 GS', $types[0]->name);
        $this->assertSame('r1250gs', $types[0]->value);
        $this->assertSame($brand, $types[0]->brand);

        $this->assertSame('R 1300 GS', $types[1]->name);
        $this->assertSame('r1300gs', $types[1]->value);
        $this->assertSame($brand, $types[1]->brand);
    }

    public function test_search_parses_result_listings(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');
        $type = new Type(name: 'R 1250 GS', value: 'r1250gs', brand: $brand);

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParam brand
            $this->okResponse(), // setSessionParam type
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $searchResult = $client->search(new SearchCriteria(brand: $brand, type: $type));

        $this->assertInstanceOf(SearchResult::class, $searchResult);
        $this->assertCount(2, $searchResult->results);
        $this->assertSame(42, $searchResult->totalCount);

        // Pagination metadata
        $this->assertSame(1, $searchResult->currentPage);
        $this->assertSame(50, $searchResult->perPage);
        $this->assertSame(1, $searchResult->totalPages());
        $this->assertFalse($searchResult->hasNextPage());

        $results = $searchResult->results;

        // First result
        $this->assertSame('BMW', $results[0]->brand);
        $this->assertSame('R 1250 GS Adventure', $results[0]->model);
        $this->assertSame(18950, $results[0]->price);
        $this->assertSame(2021, $results[0]->year);
        $this->assertSame(25000, $results[0]->odometerReading);
        $this->assertSame('KM', $results[0]->odometerReadingUnit);
        $this->assertSame('https://www.motoroccasion.nl/fotos/12345/thumb.jpg', $results[0]->image);
        $this->assertSame('/motor/12345/bmw-r-1250-gs', $results[0]->url);
        $this->assertSame('De Motor Shop', $results[0]->seller->name);
        $this->assertSame(Province::NoordHolland, $results[0]->seller->province);
        $this->assertSame('https://www.dealer.nl', $results[0]->seller->website);

        // Second result
        $this->assertSame('BMW', $results[1]->brand);
        $this->assertSame('R 1300 GS', $results[1]->model);
        $this->assertSame(22500, $results[1]->price);
        $this->assertSame(2024, $results[1]->year);
        $this->assertSame(1500, $results[1]->odometerReading);
        $this->assertSame('KM', $results[1]->odometerReadingUnit);
        $this->assertSame('Particulier', $results[1]->seller->name);
        $this->assertSame(Province::ZuidHolland, $results[1]->seller->province);
    }

    public function test_search_with_custom_pagination(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParam brand
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php page 2
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $searchResult = $client->search(new SearchCriteria(brand: $brand, page: 2, perPage: 10));

        $this->assertSame(2, $searchResult->currentPage);
        $this->assertSame(10, $searchResult->perPage);
        $this->assertSame(5, $searchResult->totalPages()); // ceil(42/10) = 5
        $this->assertTrue($searchResult->hasNextPage());
    }

    public function test_search_result_last_page_has_no_next(): void
    {
        $searchResult = new SearchResult(
            results: [],
            totalCount: 42,
            currentPage: 3,
            perPage: 20,
        );

        $this->assertSame(3, $searchResult->totalPages());
        $this->assertFalse($searchResult->hasNextPage());
    }

    public function test_search_result_single_page(): void
    {
        $searchResult = new SearchResult(
            results: [],
            totalCount: 5,
            currentPage: 1,
            perPage: 20,
        );

        $this->assertSame(1, $searchResult->totalPages());
        $this->assertFalse($searchResult->hasNextPage());
    }

    public function test_search_result_zero_total(): void
    {
        $searchResult = new SearchResult(
            results: [],
            totalCount: 0,
            currentPage: 1,
            perPage: 20,
        );

        $this->assertSame(1, $searchResult->totalPages());
        $this->assertFalse($searchResult->hasNextPage());
    }

    public function test_get_categories_parses_homepage_select(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $categories = $client->getCategories();

        $this->assertCount(17, $categories);

        $this->assertInstanceOf(Category::class, $categories[0]);
        $this->assertSame('All Off Road', $categories[0]->name);
        $this->assertSame('1', $categories[0]->value);

        $this->assertSame('Naked', $categories[6]->name);
        $this->assertSame('43', $categories[6]->value);

        $this->assertSame('Zijspan', $categories[16]->name);
        $this->assertSame('28', $categories[16]->value);
    }

    public function test_get_categories_skips_placeholder(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $categories = $client->getCategories();

        foreach ($categories as $category) {
            $this->assertNotSame('-1', $category->value);
            $this->assertNotSame('Kies een categorie', $category->name);
        }
    }

    public function test_get_detail_parses_listing_page(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->detailFixture()),
        ]);

        $result = new Result(
            brand: 'BMW',
            model: 'R 4',
            price: 12500,
            year: 1936,
            odometerReading: 60949,
            odometerReadingUnit: 'KM',
            image: 'https://www.motoroccasion.nl/fotos/99999/thumb.jpg',
            url: '/motor/99999/bmw-r-4',
            seller: new Seller(
                name: 'MotoPort Goes',
                province: Province::Zeeland,
                website: 'https://www.motoport-goes.nl',
            ),
        );

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $detail = $client->getDetail($result);

        $this->assertInstanceOf(ListingDetail::class, $detail);

        // Brand and model
        $this->assertSame('BMW', $detail->brand);
        $this->assertSame('R 4', $detail->model);

        // Pricing
        $this->assertSame(12500, $detail->price);
        $this->assertSame(13950, $detail->originalPrice);
        $this->assertSame(235, $detail->monthlyLease);

        // Specs from header
        $this->assertSame(1936, $detail->year);
        $this->assertSame(60949, $detail->odometerReading);
        $this->assertSame('KM', $detail->odometerReadingUnit);
        $this->assertSame('ZWART', $detail->color);
        $this->assertSame(18, $detail->powerKw);
        $this->assertSame('A', $detail->license);
        $this->assertTrue($detail->warranty);

        // Images
        $this->assertCount(3, $detail->images);
        $this->assertSame('https://www.motoroccasion.nl/fotos/99999/photo1.jpg', $detail->images[0]);
        $this->assertSame('https://www.motoroccasion.nl/fotos/99999/photo2.jpg', $detail->images[1]);
        $this->assertSame('https://www.motoroccasion.nl/fotos/99999/photo3.jpg', $detail->images[2]);

        // Description
        $this->assertSame('Dit is een prachtige BMW R 4 uit 1936 in uitstekende staat.', $detail->description);

        // Specifications
        $this->assertSame('BMW', $detail->specifications['Merk']);
        $this->assertSame('R 4', $detail->specifications['Model']);
        $this->assertSame('1936', $detail->specifications['Bouwjaar']);
        $this->assertSame('400 cc', $detail->specifications['Cilinderinhoud']);

        // URL
        $this->assertSame('/motor/99999/bmw-r-4', $detail->url);

        // Seller
        $this->assertSame('MotoPort Goes', $detail->seller->name);
        $this->assertNull($detail->seller->province);
        $this->assertSame('https://www.motoport-goes.nl', $detail->seller->website);
        $this->assertSame('Nobelweg 4', $detail->seller->address);
        $this->assertSame('Goes', $detail->seller->city);
        $this->assertSame('0113-231640', $detail->seller->phone);
    }

    public function test_get_offers_parses_big_tile_listings(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->offersFixture()), // AJAX /ms.php
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $searchResult = $client->getOffers();

        $this->assertInstanceOf(SearchResult::class, $searchResult);
        $this->assertCount(3, $searchResult->results);
        $this->assertSame(1329, $searchResult->totalCount);

        // Pagination metadata
        $this->assertSame(1, $searchResult->currentPage);
        $this->assertSame(40, $searchResult->perPage);
        $this->assertSame(34, $searchResult->totalPages()); // ceil(1329/40) = 34
        $this->assertTrue($searchResult->hasNextPage());

        $results = $searchResult->results;

        // First result: new bike
        $this->assertSame('SUZUKI', $results[0]->brand);
        $this->assertSame('V-STROM 800', $results[0]->model);
        $this->assertSame(10990, $results[0]->price);
        $this->assertSame(11999, $results[0]->originalPrice);
        $this->assertSame(134, $results[0]->monthlyLease);
        $this->assertSame(2025, $results[0]->year);
        $this->assertSame(0, $results[0]->odometerReading);
        $this->assertSame('KM', $results[0]->odometerReadingUnit);
        $this->assertSame('https://cdn.example.com/suzuki-vstrom.jpg', $results[0]->image);
        $this->assertSame('/motoren/suzuki-v-strom-800-m1598887.html', $results[0]->url);
        $this->assertSame('Goedhart Motoren', $results[0]->seller->name);

        // Second result: used bike with mileage
        $this->assertSame('KTM', $results[1]->brand);
        $this->assertSame('350 EXC F', $results[1]->model);
        $this->assertSame(7990, $results[1]->price);
        $this->assertSame(8350, $results[1]->originalPrice);
        $this->assertSame(136, $results[1]->monthlyLease);
        $this->assertSame(2020, $results[1]->year);
        $this->assertSame(9270, $results[1]->odometerReading);
        $this->assertSame('KM', $results[1]->odometerReadingUnit);
        $this->assertSame('/motoren/ktm-350-exc-f-m1615047.html', $results[1]->url);
        $this->assertSame('Mulders Motorcycles', $results[1]->seller->name);

        // Third result: old cheap bike
        $this->assertSame('KAWASAKI', $results[2]->brand);
        $this->assertSame('GPX 600 R', $results[2]->model);
        $this->assertSame(600, $results[2]->price);
        $this->assertSame(1250, $results[2]->originalPrice);
        $this->assertSame(12, $results[2]->monthlyLease);
        $this->assertSame(1991, $results[2]->year);
        $this->assertSame(23000, $results[2]->odometerReading);
        $this->assertSame('Gilex Motoren', $results[2]->seller->name);
    }

    public function test_get_offers_with_custom_pagination(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->offersFixture()), // AJAX /ms.php page 2
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $searchResult = $client->getOffers(page: 2, perPage: 20);

        $this->assertSame(2, $searchResult->currentPage);
        $this->assertSame(20, $searchResult->perPage);
        $this->assertSame(67, $searchResult->totalPages()); // ceil(1329/20) = 67
        $this->assertTrue($searchResult->hasNextPage());
    }

    public function test_result_dto_round_trip_with_offer_fields(): void
    {
        $result = new Result(
            brand: 'SUZUKI',
            model: 'V-STROM 800',
            price: 10990,
            year: 2025,
            odometerReading: 0,
            odometerReadingUnit: 'KM',
            image: 'https://cdn.example.com/suzuki.jpg',
            url: '/motoren/suzuki-v-strom-800-m1598887.html',
            seller: new Seller(
                name: 'Goedhart Motoren',
                province: null,
                website: '',
            ),
            originalPrice: 11999,
            monthlyLease: 134,
        );

        $array = $result->toArray();

        $this->assertSame(11999, $array['originalPrice']);
        $this->assertSame(134, $array['monthlyLease']);
        $this->assertNull($array['seller']['province']);

        $reconstructed = Result::fromArray($array);

        $this->assertSame(11999, $reconstructed->originalPrice);
        $this->assertSame(134, $reconstructed->monthlyLease);
        $this->assertSame('SUZUKI', $reconstructed->brand);
        $this->assertSame(10990, $reconstructed->price);
        $this->assertNull($reconstructed->seller->province);
    }

    public function test_result_dto_round_trip_without_offer_fields(): void
    {
        $result = new Result(
            brand: 'BMW',
            model: 'R 1250 GS',
            price: 18950,
            year: 2021,
            odometerReading: 25000,
            odometerReadingUnit: 'KM',
            image: 'https://example.com/bmw.jpg',
            url: '/motor/12345/bmw-r-1250-gs',
            seller: new Seller(
                name: 'De Motor Shop',
                province: Province::NoordHolland,
                website: 'https://www.dealer.nl',
            ),
        );

        $array = $result->toArray();

        $this->assertNull($array['originalPrice']);
        $this->assertNull($array['monthlyLease']);
        $this->assertSame('Noord-Holland', $array['seller']['province']);

        $reconstructed = Result::fromArray($array);

        $this->assertNull($reconstructed->originalPrice);
        $this->assertNull($reconstructed->monthlyLease);
        $this->assertSame('BMW', $reconstructed->brand);
        $this->assertSame(Province::NoordHolland, $reconstructed->seller->province);
    }
}
