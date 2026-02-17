<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Category;
use NiekNijland\MotorOccasion\Data\LicenseCategory;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\OdometerUnit;
use NiekNijland\MotorOccasion\Data\Province;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\SearchCriteria;
use NiekNijland\MotorOccasion\Data\SearchResult;
use NiekNijland\MotorOccasion\Data\Seller;
use NiekNijland\MotorOccasion\Data\Type;
use NiekNijland\MotorOccasion\Exception\MotorOccasionException;
use NiekNijland\MotorOccasion\MotorOccasion;
use NiekNijland\MotorOccasion\MotorOccasionInterface;
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

    private function resultsPaginationFixture(): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/results-pagination.html');
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
        $this->assertSame(OdometerUnit::Kilometers, $results[0]->odometerReadingUnit);
        $this->assertSame('https://www.motoroccasion.nl/fotos/12345/thumb.jpg', $results[0]->image);
        $this->assertSame('https://www.motoroccasion.nl/motor/12345/bmw-r-1250-gs', $results[0]->url);
        $this->assertSame(12345, $results[0]->id);
        $this->assertSame('De Motor Shop', $results[0]->seller->name);
        $this->assertSame(Province::NoordHolland, $results[0]->seller->province);
        $this->assertSame('https://www.dealer.nl', $results[0]->seller->website);

        // Second result
        $this->assertSame('BMW', $results[1]->brand);
        $this->assertSame('R 1300 GS', $results[1]->model);
        $this->assertSame(22500, $results[1]->price);
        $this->assertSame(2024, $results[1]->year);
        $this->assertSame(1500, $results[1]->odometerReading);
        $this->assertSame(OdometerUnit::Kilometers, $results[1]->odometerReadingUnit);
        $this->assertSame(67890, $results[1]->id);
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

    public function test_search_estimates_total_count_from_pagination_buttons(): void
    {
        $brand = new Brand(name: 'BMW', value: '4');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParam brand
            $this->okResponse($this->resultsPaginationFixture()), // AJAX /mz.php
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $searchResult = $client->search(new SearchCriteria(brand: $brand, perPage: 10));

        $this->assertInstanceOf(SearchResult::class, $searchResult);
        $this->assertCount(2, $searchResult->results);

        // Estimated from pagination: max offset 2910 + perPage 10 = 2920
        $this->assertSame(2920, $searchResult->totalCount);
        $this->assertSame(292, $searchResult->totalPages()); // ceil(2920/10)
        $this->assertTrue($searchResult->hasNextPage());
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
            odometerReadingUnit: OdometerUnit::Kilometers,
            image: 'https://www.motoroccasion.nl/fotos/99999/thumb.jpg',
            url: 'https://www.motoroccasion.nl/motor/99999/bmw-r-4',
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
        $this->assertSame(OdometerUnit::Kilometers, $detail->odometerReadingUnit);
        $this->assertSame('ZWART', $detail->color);
        $this->assertSame(18, $detail->powerKw);
        $this->assertSame(LicenseCategory::A, $detail->license);
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

        // URL and ID
        $this->assertSame('https://www.motoroccasion.nl/motor/99999/bmw-r-4', $detail->url);
        $this->assertSame(99999, $detail->id);

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
        $this->assertSame(OdometerUnit::Kilometers, $results[0]->odometerReadingUnit);
        $this->assertSame('https://cdn.example.com/suzuki-vstrom.jpg', $results[0]->image);
        $this->assertSame('https://www.motoroccasion.nl/motoren/suzuki-v-strom-800-m1598887.html', $results[0]->url);
        $this->assertSame(1598887, $results[0]->id);
        $this->assertSame('Goedhart Motoren', $results[0]->seller->name);

        // Second result: used bike with mileage
        $this->assertSame('KTM', $results[1]->brand);
        $this->assertSame('350 EXC F', $results[1]->model);
        $this->assertSame(7990, $results[1]->price);
        $this->assertSame(8350, $results[1]->originalPrice);
        $this->assertSame(136, $results[1]->monthlyLease);
        $this->assertSame(2020, $results[1]->year);
        $this->assertSame(9270, $results[1]->odometerReading);
        $this->assertSame(OdometerUnit::Kilometers, $results[1]->odometerReadingUnit);
        $this->assertSame('https://www.motoroccasion.nl/motoren/ktm-350-exc-f-m1615047.html', $results[1]->url);
        $this->assertSame(1615047, $results[1]->id);
        $this->assertSame('Mulders Motorcycles', $results[1]->seller->name);

        // Third result: old cheap bike
        $this->assertSame('KAWASAKI', $results[2]->brand);
        $this->assertSame('GPX 600 R', $results[2]->model);
        $this->assertSame(600, $results[2]->price);
        $this->assertSame(1250, $results[2]->originalPrice);
        $this->assertSame(12, $results[2]->monthlyLease);
        $this->assertSame(1991, $results[2]->year);
        $this->assertSame(23000, $results[2]->odometerReading);
        $this->assertSame(1452851, $results[2]->id);
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
            odometerReadingUnit: OdometerUnit::Kilometers,
            image: 'https://cdn.example.com/suzuki.jpg',
            url: 'https://www.motoroccasion.nl/motoren/suzuki-v-strom-800-m1598887.html',
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
        $this->assertSame('KM', $array['odometerReadingUnit']);
        $this->assertNull($array['seller']['province']);

        $reconstructed = Result::fromArray($array);

        $this->assertSame(11999, $reconstructed->originalPrice);
        $this->assertSame(134, $reconstructed->monthlyLease);
        $this->assertSame('SUZUKI', $reconstructed->brand);
        $this->assertSame(10990, $reconstructed->price);
        $this->assertSame(OdometerUnit::Kilometers, $reconstructed->odometerReadingUnit);
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
            odometerReadingUnit: OdometerUnit::Kilometers,
            image: 'https://example.com/bmw.jpg',
            url: 'https://www.motoroccasion.nl/motor/12345/bmw-r-1250-gs',
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

    // --- Interface implementation ---

    public function test_motor_occasion_implements_interface(): void
    {
        $mock = new MockHandler([]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $this->assertInstanceOf(MotorOccasionInterface::class, $client);
    }

    // --- Guzzle exception wrapping ---

    public function test_session_network_error_throws_motor_occasion_exception(): void
    {
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('GET', 'https://www.motoroccasion.nl')),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('HTTP request failed while establishing session');

        $client->getBrands();
    }

    public function test_search_form_network_error_throws_motor_occasion_exception(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            new ConnectException('Timeout', new Request('GET', 'https://www.motoroccasion.nl/fs.php')),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('HTTP request failed while fetching search form');

        $client->getBrands();
    }

    public function test_detail_network_error_throws_motor_occasion_exception(): void
    {
        $result = new Result(
            brand: 'BMW',
            model: 'R 4',
            price: 12500,
            year: 1936,
            odometerReading: 60949,
            odometerReadingUnit: OdometerUnit::Kilometers,
            image: 'https://example.com/thumb.jpg',
            url: 'https://www.motoroccasion.nl/motor/99999/bmw-r-4',
            seller: new Seller(name: 'Test', province: null, website: ''),
        );

        $mock = new MockHandler([
            $this->sessionResponse(),
            new ConnectException('DNS failure', new Request('GET', 'https://www.motoroccasion.nl/motor/99999/bmw-r-4')),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('HTTP request failed for detail page');

        $client->getDetail($result);
    }

    public function test_search_network_error_during_param_set_throws_motor_occasion_exception(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');

        $mock = new MockHandler([
            $this->sessionResponse(),
            new ConnectException('Timeout', new Request('GET', 'https://www.motoroccasion.nl/mz.php')),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('HTTP request failed while setting search parameter');

        $client->search(new SearchCriteria(brand: $brand));
    }

    public function test_guzzle_exception_preserves_previous_exception(): void
    {
        $originalException = new ConnectException('Connection refused', new Request('GET', 'https://www.motoroccasion.nl'));

        $mock = new MockHandler([$originalException]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        try {
            $client->getBrands();
            $this->fail('Expected MotorOccasionException was not thrown');
        } catch (MotorOccasionException $motorOccasionException) {
            $this->assertSame($originalException, $motorOccasionException->getPrevious());
        }
    }

    // --- HTTP status code errors ---

    public function test_session_non_200_throws_motor_occasion_exception(): void
    {
        $mock = new MockHandler([
            new Response(503, [], 'Service Unavailable'),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('HTTP request failed while establishing session');

        $client->getBrands();
    }

    public function test_detail_non_200_throws_motor_occasion_exception(): void
    {
        $result = new Result(
            brand: 'BMW',
            model: 'R 4',
            price: 12500,
            year: 1936,
            odometerReading: 60949,
            odometerReadingUnit: OdometerUnit::Kilometers,
            image: 'https://example.com/thumb.jpg',
            url: 'https://www.motoroccasion.nl/motor/99999/bmw-r-4',
            seller: new Seller(name: 'Test', province: null, website: ''),
        );

        $mock = new MockHandler([
            $this->sessionResponse(),
            new Response(404, [], 'Not Found'),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('HTTP request failed for detail page');

        $client->getDetail($result);
    }

    // --- Clock injection ---

    public function test_clock_injection_uses_provided_timestamp(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParam brand
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php
        ]);

        $fixedTime = 1700000000;

        $client = new MotorOccasion(
            httpClient: $this->createClientWithMock($mock),
            clock: fn (): int => $fixedTime,
        );

        $searchResult = $client->search(new SearchCriteria(brand: $brand));

        // The search completed successfully, proving the clock was used without errors
        $this->assertInstanceOf(SearchResult::class, $searchResult);
    }

    // --- Reset session ---

    public function test_reset_session_clears_state(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->sessionResponse(), // second session after reset
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        // First call establishes session
        $client->getCategories();

        // Reset clears it
        $client->resetSession();

        // Next call needs a new session (consumes second response)
        $categories = $client->getCategories();

        $this->assertCount(17, $categories);
    }

    public function test_reset_session_invalidates_external_cache(): void
    {
        $cache = new ArrayCache();

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->searchFormFixture()),
        ]);

        $client = new MotorOccasion(
            httpClient: $this->createClientWithMock($mock),
            cache: $cache,
        );

        // Populate cache
        $brands = $client->getBrands();
        $this->assertNotEmpty($brands);
        $this->assertNotNull($cache->get('motor-occasion:brands'));

        // Reset should clear external cache
        $client->resetSession();

        $this->assertNull($cache->get('motor-occasion:brands'));
        $this->assertNull($cache->get('motor-occasion:categories'));
    }

    public function test_search_does_not_invalidate_external_cache(): void
    {
        $cache = new ArrayCache();

        $brand = new Brand(name: 'BMW', value: 'bmw');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->searchFormFixture()),
            // search() creates a new session internally
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParam brand
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php
        ]);

        $client = new MotorOccasion(
            httpClient: $this->createClientWithMock($mock),
            cache: $cache,
        );

        // Populate the brands cache
        $brands = $client->getBrands();
        $this->assertCount(3, $brands);
        $this->assertNotNull($cache->get('motor-occasion:brands'));

        // search() should NOT wipe the external cache
        $searchResult = $client->search(new SearchCriteria(brand: $brand));
        $this->assertInstanceOf(SearchResult::class, $searchResult);

        // Brands cache should still be intact
        $this->assertNotNull($cache->get('motor-occasion:brands'), 'search() should not invalidate the external brands cache');
    }

    // --- Province enum ---

    public function test_province_try_from_abbreviation_known_abbreviations(): void
    {
        $this->assertSame(Province::Drenthe, Province::tryFromAbbreviation('DR'));
        $this->assertSame(Province::Drenthe, Province::tryFromAbbreviation('DRE'));
        $this->assertSame(Province::Flevoland, Province::tryFromAbbreviation('FL'));
        $this->assertSame(Province::Flevoland, Province::tryFromAbbreviation('FLE'));
        $this->assertSame(Province::Friesland, Province::tryFromAbbreviation('FR'));
        $this->assertSame(Province::Friesland, Province::tryFromAbbreviation('FRI'));
        $this->assertSame(Province::Gelderland, Province::tryFromAbbreviation('GLD'));
        $this->assertSame(Province::Groningen, Province::tryFromAbbreviation('GR'));
        $this->assertSame(Province::Groningen, Province::tryFromAbbreviation('GRO'));
        $this->assertSame(Province::Limburg, Province::tryFromAbbreviation('LIM'));
        $this->assertSame(Province::NoordBrabant, Province::tryFromAbbreviation('BRA'));
        $this->assertSame(Province::NoordBrabant, Province::tryFromAbbreviation('NB'));
        $this->assertSame(Province::NoordBrabant, Province::tryFromAbbreviation('N-B'));
        $this->assertSame(Province::NoordHolland, Province::tryFromAbbreviation('NH'));
        $this->assertSame(Province::NoordHolland, Province::tryFromAbbreviation('N-H'));
        $this->assertSame(Province::Overijssel, Province::tryFromAbbreviation('OV'));
        $this->assertSame(Province::Overijssel, Province::tryFromAbbreviation('OVE'));
        $this->assertSame(Province::Utrecht, Province::tryFromAbbreviation('UTR'));
        $this->assertSame(Province::Utrecht, Province::tryFromAbbreviation('UT'));
        $this->assertSame(Province::Zeeland, Province::tryFromAbbreviation('Z'));
        $this->assertSame(Province::Zeeland, Province::tryFromAbbreviation('ZE'));
        $this->assertSame(Province::Zeeland, Province::tryFromAbbreviation('ZEE'));
        $this->assertSame(Province::ZuidHolland, Province::tryFromAbbreviation('ZH'));
        $this->assertSame(Province::ZuidHolland, Province::tryFromAbbreviation('Z-H'));
    }

    public function test_province_try_from_abbreviation_is_case_insensitive(): void
    {
        $this->assertSame(Province::NoordHolland, Province::tryFromAbbreviation('nh'));
        $this->assertSame(Province::NoordHolland, Province::tryFromAbbreviation('Nh'));
        $this->assertSame(Province::ZuidHolland, Province::tryFromAbbreviation('zh'));
    }

    public function test_province_try_from_abbreviation_trims_whitespace(): void
    {
        $this->assertSame(Province::NoordHolland, Province::tryFromAbbreviation('  NH  '));
    }

    public function test_province_try_from_abbreviation_unknown_returns_null(): void
    {
        $this->assertNull(Province::tryFromAbbreviation('XX'));
        $this->assertNull(Province::tryFromAbbreviation(''));
        $this->assertNull(Province::tryFromAbbreviation('UNKNOWN'));
    }

    // --- OdometerUnit enum ---

    public function test_odometer_unit_enum_values(): void
    {
        $this->assertSame('KM', OdometerUnit::Kilometers->value);
        $this->assertSame('MI', OdometerUnit::Miles->value);
    }

    public function test_odometer_unit_from_valid_values(): void
    {
        $this->assertSame(OdometerUnit::Kilometers, OdometerUnit::from('KM'));
        $this->assertSame(OdometerUnit::Miles, OdometerUnit::from('MI'));
    }

    public function test_odometer_unit_try_from_invalid_returns_null(): void
    {
        $this->assertNull(OdometerUnit::tryFrom('INVALID'));
        $this->assertNull(OdometerUnit::tryFrom(''));
        $this->assertNull(OdometerUnit::tryFrom('km'));
    }

    // --- LicenseCategory enum ---

    public function test_license_category_enum_values(): void
    {
        $this->assertSame('A', LicenseCategory::A->value);
        $this->assertSame('A1', LicenseCategory::A1->value);
        $this->assertSame('A2', LicenseCategory::A2->value);
        $this->assertSame('AM', LicenseCategory::AM->value);
        $this->assertSame('B', LicenseCategory::B->value);
    }

    public function test_license_category_from_valid_values(): void
    {
        $this->assertSame(LicenseCategory::A, LicenseCategory::from('A'));
        $this->assertSame(LicenseCategory::A1, LicenseCategory::from('A1'));
        $this->assertSame(LicenseCategory::A2, LicenseCategory::from('A2'));
        $this->assertSame(LicenseCategory::AM, LicenseCategory::from('AM'));
        $this->assertSame(LicenseCategory::B, LicenseCategory::from('B'));
    }

    public function test_license_category_try_from_invalid_returns_null(): void
    {
        $this->assertNull(LicenseCategory::tryFrom('INVALID'));
        $this->assertNull(LicenseCategory::tryFrom(''));
        $this->assertNull(LicenseCategory::tryFrom('C'));
    }

    // --- Brand DTO serialization ---

    public function test_brand_dto_round_trip(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');

        $array = $brand->toArray();

        $this->assertSame('BMW', $array['name']);
        $this->assertSame('bmw', $array['value']);

        $reconstructed = Brand::fromArray($array);

        $this->assertSame('BMW', $reconstructed->name);
        $this->assertSame('bmw', $reconstructed->value);
    }

    // --- Category DTO serialization ---

    public function test_category_dto_round_trip(): void
    {
        $category = new Category(name: 'Naked', value: '43');

        $array = $category->toArray();

        $this->assertSame('Naked', $array['name']);
        $this->assertSame('43', $array['value']);

        $reconstructed = Category::fromArray($array);

        $this->assertSame('Naked', $reconstructed->name);
        $this->assertSame('43', $reconstructed->value);
    }

    // --- Type DTO serialization ---

    public function test_type_dto_round_trip(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');
        $type = new Type(name: 'R 1250 GS', value: 'r1250gs', brand: $brand);

        $array = $type->toArray();

        $this->assertSame('R 1250 GS', $array['name']);
        $this->assertSame('r1250gs', $array['value']);
        $this->assertSame('BMW', $array['brand']['name']);
        $this->assertSame('bmw', $array['brand']['value']);

        $reconstructed = Type::fromArray($array);

        $this->assertSame('R 1250 GS', $reconstructed->name);
        $this->assertSame('r1250gs', $reconstructed->value);
        $this->assertSame('BMW', $reconstructed->brand->name);
        $this->assertSame('bmw', $reconstructed->brand->value);
    }

    // --- ListingDetail DTO serialization ---

    public function test_listing_detail_dto_round_trip(): void
    {
        $detail = new ListingDetail(
            brand: 'BMW',
            model: 'R 4',
            price: 12500,
            originalPrice: 13950,
            monthlyLease: 235,
            year: 1936,
            odometerReading: 60949,
            odometerReadingUnit: OdometerUnit::Kilometers,
            color: 'ZWART',
            powerKw: 18,
            license: LicenseCategory::A,
            warranty: true,
            images: ['https://example.com/photo1.jpg', 'https://example.com/photo2.jpg'],
            description: 'A beautiful motorcycle.',
            specifications: ['Merk' => 'BMW', 'Model' => 'R 4'],
            url: 'https://www.motoroccasion.nl/motor/99999/bmw-r-4',
            seller: new Seller(
                name: 'MotoPort Goes',
                province: null,
                website: 'https://www.motoport-goes.nl',
                address: 'Nobelweg 4',
                city: 'Goes',
                phone: '0113-231640',
            ),
        );

        $array = $detail->toArray();

        $this->assertSame('BMW', $array['brand']);
        $this->assertSame(12500, $array['price']);
        $this->assertSame(13950, $array['originalPrice']);
        $this->assertSame(235, $array['monthlyLease']);
        $this->assertSame('KM', $array['odometerReadingUnit']);
        $this->assertSame('A', $array['license']);
        $this->assertSame('ZWART', $array['color']);
        $this->assertSame(18, $array['powerKw']);
        $this->assertTrue($array['warranty']);
        $this->assertCount(2, $array['images']);
        $this->assertSame('BMW', $array['specifications']['Merk']);
        $this->assertSame('MotoPort Goes', $array['seller']['name']);
        $this->assertSame('Nobelweg 4', $array['seller']['address']);

        $reconstructed = ListingDetail::fromArray($array);

        $this->assertSame('BMW', $reconstructed->brand);
        $this->assertSame('R 4', $reconstructed->model);
        $this->assertSame(12500, $reconstructed->price);
        $this->assertSame(13950, $reconstructed->originalPrice);
        $this->assertSame(235, $reconstructed->monthlyLease);
        $this->assertSame(1936, $reconstructed->year);
        $this->assertSame(60949, $reconstructed->odometerReading);
        $this->assertSame(OdometerUnit::Kilometers, $reconstructed->odometerReadingUnit);
        $this->assertSame('ZWART', $reconstructed->color);
        $this->assertSame(18, $reconstructed->powerKw);
        $this->assertSame(LicenseCategory::A, $reconstructed->license);
        $this->assertTrue($reconstructed->warranty);
        $this->assertCount(2, $reconstructed->images);
        $this->assertSame('A beautiful motorcycle.', $reconstructed->description);
        $this->assertSame('BMW', $reconstructed->specifications['Merk']);
        $this->assertSame('https://www.motoroccasion.nl/motor/99999/bmw-r-4', $reconstructed->url);
        $this->assertSame('MotoPort Goes', $reconstructed->seller->name);
        $this->assertSame('0113-231640', $reconstructed->seller->phone);
    }

    public function test_listing_detail_dto_round_trip_with_null_optionals(): void
    {
        $detail = new ListingDetail(
            brand: 'Honda',
            model: 'CB500F',
            price: 5000,
            originalPrice: null,
            monthlyLease: null,
            year: 2020,
            odometerReading: 15000,
            odometerReadingUnit: OdometerUnit::Kilometers,
            color: null,
            powerKw: null,
            license: null,
            warranty: null,
            images: [],
            description: null,
            specifications: [],
            url: 'https://www.motoroccasion.nl/motor/11111/honda-cb500f',
            seller: new Seller(name: 'Particulier', province: null, website: ''),
        );

        $array = $detail->toArray();

        $this->assertNull($array['originalPrice']);
        $this->assertNull($array['monthlyLease']);
        $this->assertNull($array['color']);
        $this->assertNull($array['powerKw']);
        $this->assertNull($array['warranty']);
        $this->assertNull($array['description']);

        $reconstructed = ListingDetail::fromArray($array);

        $this->assertNull($reconstructed->originalPrice);
        $this->assertNull($reconstructed->color);
        $this->assertNull($reconstructed->warranty);
        $this->assertSame('Honda', $reconstructed->brand);
    }

    // --- Seller DTO serialization ---

    public function test_seller_dto_round_trip_with_all_fields(): void
    {
        $seller = new Seller(
            name: 'MotoPort Goes',
            province: Province::Zeeland,
            website: 'https://www.motoport-goes.nl',
            address: 'Nobelweg 4',
            city: 'Goes',
            phone: '0113-231640',
        );

        $array = $seller->toArray();

        $this->assertSame('Zeeland', $array['province']);
        $this->assertSame('Nobelweg 4', $array['address']);
        $this->assertSame('Goes', $array['city']);
        $this->assertSame('0113-231640', $array['phone']);

        $reconstructed = Seller::fromArray($array);

        $this->assertSame(Province::Zeeland, $reconstructed->province);
        $this->assertSame('Nobelweg 4', $reconstructed->address);
        $this->assertSame('Goes', $reconstructed->city);
        $this->assertSame('0113-231640', $reconstructed->phone);
    }

    public function test_seller_dto_round_trip_with_null_optionals(): void
    {
        $seller = new Seller(
            name: 'Particulier',
            province: null,
            website: '',
        );

        $array = $seller->toArray();

        $this->assertNull($array['province']);
        $this->assertNull($array['address']);
        $this->assertNull($array['city']);
        $this->assertNull($array['phone']);

        $reconstructed = Seller::fromArray($array);

        $this->assertNull($reconstructed->province);
        $this->assertNull($reconstructed->address);
        $this->assertNull($reconstructed->city);
        $this->assertNull($reconstructed->phone);
        $this->assertSame('Particulier', $reconstructed->name);
    }

    // --- Caching ---

    public function test_get_brands_returns_cached_result(): void
    {
        $cachedBrands = [
            new Brand(name: 'Cached BMW', value: 'bmw'),
            new Brand(name: 'Cached Honda', value: 'honda'),
        ];

        $cache = new ArrayCache();
        $cache->set('motor-occasion:brands', $cachedBrands);

        // No HTTP responses queued — if cache misses, it would fail
        $mock = new MockHandler([]);

        $client = new MotorOccasion(
            httpClient: $this->createClientWithMock($mock),
            cache: $cache,
        );

        $brands = $client->getBrands();

        $this->assertCount(2, $brands);
        $this->assertSame('Cached BMW', $brands[0]->name);
        $this->assertSame('Cached Honda', $brands[1]->name);
    }

    public function test_get_brands_stores_result_in_cache(): void
    {
        $cache = new ArrayCache();

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->searchFormFixture()),
        ]);

        $client = new MotorOccasion(
            httpClient: $this->createClientWithMock($mock),
            cache: $cache,
        );

        $brands = $client->getBrands();

        $this->assertCount(3, $brands);

        // Verify it was cached
        $cached = $cache->get('motor-occasion:brands');

        $this->assertNotNull($cached);
        $this->assertCount(3, $cached);
        $this->assertSame('BMW', $cached[0]->name);
    }

    public function test_get_categories_returns_cached_result(): void
    {
        $cachedCategories = [
            new Category(name: 'Cached Naked', value: '43'),
        ];

        $cache = new ArrayCache();
        $cache->set('motor-occasion:categories', $cachedCategories);

        $mock = new MockHandler([]);

        $client = new MotorOccasion(
            httpClient: $this->createClientWithMock($mock),
            cache: $cache,
        );

        $categories = $client->getCategories();

        $this->assertCount(1, $categories);
        $this->assertSame('Cached Naked', $categories[0]->name);
    }

    public function test_get_categories_stores_result_in_cache(): void
    {
        $cache = new ArrayCache();

        $mock = new MockHandler([
            $this->sessionResponse(),
        ]);

        $client = new MotorOccasion(
            httpClient: $this->createClientWithMock($mock),
            cache: $cache,
        );

        $categories = $client->getCategories();

        $this->assertCount(17, $categories);

        $cached = $cache->get('motor-occasion:categories');

        $this->assertNotNull($cached);
        $this->assertCount(17, $cached);
    }

    // --- fromArray() invalid enum values ---

    public function test_result_from_array_invalid_odometer_unit_throws_motor_occasion_exception(): void
    {
        $data = [
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'price' => 18950,
            'year' => 2021,
            'odometerReading' => 25000,
            'odometerReadingUnit' => 'INVALID',
            'image' => 'https://example.com/bmw.jpg',
            'url' => 'https://www.motoroccasion.nl/motor/12345/bmw-r-1250-gs',
            'seller' => [
                'name' => 'Test',
                'province' => null,
                'website' => '',
            ],
        ];

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('Invalid odometer unit: INVALID');

        Result::fromArray($data);
    }

    public function test_listing_detail_from_array_invalid_odometer_unit_throws_motor_occasion_exception(): void
    {
        $data = [
            'brand' => 'BMW',
            'model' => 'R 4',
            'price' => 12500,
            'originalPrice' => null,
            'monthlyLease' => null,
            'year' => 1936,
            'odometerReading' => 60949,
            'odometerReadingUnit' => 'FURLONGS',
            'color' => null,
            'powerKw' => null,
            'license' => null,
            'warranty' => null,
            'images' => [],
            'description' => null,
            'specifications' => [],
            'url' => 'https://www.motoroccasion.nl/motor/99999/bmw-r-4',
            'seller' => [
                'name' => 'Test',
                'province' => null,
                'website' => '',
                'address' => null,
                'city' => null,
                'phone' => null,
            ],
        ];

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('Invalid odometer unit: FURLONGS');

        ListingDetail::fromArray($data);
    }
}
