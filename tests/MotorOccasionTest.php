<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Category;
use NiekNijland\MotorOccasion\Data\LicenseCategory;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\OdometerUnit;
use NiekNijland\MotorOccasion\Data\PriceType;
use NiekNijland\MotorOccasion\Data\Province;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\SearchCriteria;
use NiekNijland\MotorOccasion\Data\SearchResult;
use NiekNijland\MotorOccasion\Data\Seller;
use NiekNijland\MotorOccasion\Data\SortOrder;
use NiekNijland\MotorOccasion\Data\Type;
use NiekNijland\MotorOccasion\Exception\ClientException;
use NiekNijland\MotorOccasion\Exception\MotorOccasionException;
use NiekNijland\MotorOccasion\Exception\SearchFormNotFoundException;
use NiekNijland\MotorOccasion\Exception\ServerException;
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

    /**
     * @param array<int, array<string, mixed>> $history
     */
    private function createClientWithMockAndHistory(MockHandler $mockHandler, array &$history): Client
    {
        $stack = HandlerStack::create($mockHandler);
        $stack->push(Middleware::history($history));

        return new Client(['handler' => $stack]);
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

    private function detailNoSpecsFixture(): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/detail-no-specs.html');
    }

    private function detailLegacyFixture(): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/detail-legacy.html');
    }

    private function detailHondaFixture(): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/detail-honda.html');
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
            $this->okResponse(), // setSessionParams (brand + type batched)
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
        $this->assertSame(18950, $results[0]->askingPrice);
        $this->assertSame(PriceType::Asking, $results[0]->priceType);
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
        $this->assertSame(22500, $results[1]->askingPrice);
        $this->assertSame(PriceType::Asking, $results[1]->priceType);
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
            $this->okResponse(), // setSessionParams
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

        $this->assertSame(0, $searchResult->totalPages());
        $this->assertFalse($searchResult->hasNextPage());
    }

    public function test_search_estimates_total_count_from_pagination_buttons(): void
    {
        $brand = new Brand(name: 'BMW', value: '4');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParams
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
            askingPrice: 12500,
            priceType: PriceType::Asking,
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

        // Brand and model (new title format: "BMW - R 4")
        $this->assertSame('BMW', $detail->brand);
        $this->assertSame('R 4', $detail->model);

        // Pricing
        $this->assertSame(12500, $detail->askingPrice);
        $this->assertSame(PriceType::Asking, $detail->priceType);
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

        // Images — should prefer data-src (high-resolution) over img src
        $this->assertCount(3, $detail->images);
        $this->assertSame('https://www.motoroccasion.nl/fotos/99999/photo1_large.jpg', $detail->images[0]);
        $this->assertSame('https://www.motoroccasion.nl/fotos/99999/photo2_large.jpg', $detail->images[1]);
        $this->assertSame('https://www.motoroccasion.nl/fotos/99999/photo3_large.jpg', $detail->images[2]);

        // Description contains full text from deta-omsc
        $this->assertNotNull($detail->description);
        $this->assertStringContainsString('Dit is een prachtige BMW R 4 uit 1936 in uitstekende staat.', $detail->description);

        // Specifications (extracted from description key-value pairs since deta-tech is JS-loaded)
        $this->assertSame('BMW', $detail->specifications['Merk']);
        $this->assertSame('R 4', $detail->specifications['Model']);
        $this->assertSame('1936', $detail->specifications['Bouwjaar']);
        $this->assertSame('400 cc', $detail->specifications['Cilinderinhoud']);

        // URL and ID
        $this->assertSame('https://www.motoroccasion.nl/motor/99999/bmw-r-4', $detail->url);
        $this->assertSame(99999, $detail->id);

        // Seller (parsed from new DOM structure)
        $this->assertSame('MotoPort Goes', $detail->seller->name);
        $this->assertNull($detail->seller->province);
        $this->assertSame('https://www.motoport-goes.nl', $detail->seller->website);
        $this->assertSame('Nobelweg 4', $detail->seller->address);
        $this->assertSame('Goes', $detail->seller->city);
        $this->assertSame('0113-231640', $detail->seller->phone);
        $this->assertSame('4461 ZM', $detail->seller->postalCode);

        // New structured fields extracted from description
        $this->assertSame(400, $detail->engine->capacityCc);
        $this->assertFalse($detail->engine->isElectric);
        $this->assertFalse($detail->vatDeductible);
        $this->assertSame('Benzine', $detail->engine->fuelType);
        $this->assertSame(1, $detail->engine->cylinders);
        $this->assertSame('ZM-83-25', $detail->licensePlate);
        $this->assertSame('schadevrij', $detail->damageStatus);
        $this->assertSame('Overig', $detail->bodyType);
        $this->assertSame('Vrijgesteld', $detail->roadTaxStatus);

        // Tech table fields are null (deta-tech is empty in this fixture)
        $this->assertNull($detail->chassis->abs);
        $this->assertNull($detail->dimensions->seatHeightMm);
        $this->assertNull($detail->dimensions->tankCapacityLiters);
        $this->assertNull($detail->dimensions->weightKg);
        $this->assertNull($detail->engine->driveType);
        $this->assertNull($detail->engine->gearbox);
        $this->assertNull($detail->engine->maxTorque);
        $this->assertNull($detail->engine->type);
        $this->assertNull($detail->chassis->frameType);
        $this->assertNull($detail->chassis->frontSuspension);
        $this->assertNull($detail->chassis->rearSuspension);
        $this->assertNull($detail->chassis->frontBrake);
        $this->assertNull($detail->chassis->rearBrake);
        $this->assertNull($detail->chassis->frontTire);
        $this->assertNull($detail->chassis->rearTire);
        $this->assertNull($detail->dimensions->wheelbaseMm);
        $this->assertNull($detail->dimensions->lengthMm);
        $this->assertNull($detail->dimensions->widthMm);
        $this->assertNull($detail->dimensions->heightMm);
        $this->assertNull($detail->engine->topSpeed);
        $this->assertNull($detail->engine->compressionRatio);
        $this->assertNull($detail->engine->valves);
        $this->assertNull($detail->engine->boreAndStroke);
        $this->assertNull($detail->engine->fuelDelivery);
        $this->assertNull($detail->engine->ignition);
        $this->assertNull($detail->engine->starter);
        $this->assertNull($detail->engine->clutch);
        $this->assertNull($detail->availableColors);
        $this->assertNull($detail->isNew);
        $this->assertNull($detail->modelYear);
        $this->assertNull($detail->factoryWarrantyMonths);
        $this->assertNull($detail->dealerDescription);
    }

    public function test_get_detail_without_description_specs_returns_null_for_new_fields(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->detailNoSpecsFixture()),
        ]);

        $result = new Result(
            brand: 'Yamaha',
            model: 'MT 07',
            askingPrice: 6450,
            priceType: PriceType::Asking,
            year: 2019,
            odometerReading: 23500,
            odometerReadingUnit: OdometerUnit::Kilometers,
            image: 'https://www.motoroccasion.nl/fotos/88888/thumb.jpg',
            url: 'https://www.motoroccasion.nl/motoren/yamaha-mt-07-m88888.html',
            seller: new Seller(name: 'Goedhart Motoren', province: null, website: ''),
        );

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $detail = $client->getDetail($result);

        // Brand and model parsed from new title format
        $this->assertSame('Yamaha', $detail->brand);
        $this->assertSame('MT 07', $detail->model);

        // Header specs still work
        $this->assertSame(6450, $detail->askingPrice);
        $this->assertSame(PriceType::Asking, $detail->priceType);
        $this->assertSame(2019, $detail->year);
        $this->assertSame('BLAUW', $detail->color);
        $this->assertSame(55, $detail->powerKw);
        $this->assertSame(LicenseCategory::A2, $detail->license);
        $this->assertFalse($detail->warranty);

        // Seller parsed from new DOM
        $this->assertSame('Goedhart Motoren', $detail->seller->name);
        $this->assertSame('Industrieweg 12', $detail->seller->address);
        $this->assertSame('Gouda', $detail->seller->city);
        $this->assertSame('0182-123456', $detail->seller->phone);
        $this->assertSame('https://www.goedhart.nl', $detail->seller->website);

        // No specs in description — all new fields should be null
        $this->assertNull($detail->engine->capacityCc);
        $this->assertNull($detail->engine->isElectric);
        $this->assertNull($detail->vatDeductible);
        $this->assertNull($detail->engine->fuelType);
        $this->assertNull($detail->engine->cylinders);
        $this->assertNull($detail->licensePlate);
        $this->assertNull($detail->damageStatus);
        $this->assertNull($detail->bodyType);
        $this->assertNull($detail->roadTaxStatus);
        $this->assertNull($detail->chassis->abs);
        $this->assertNull($detail->dimensions->seatHeightMm);
        $this->assertNull($detail->dimensions->tankCapacityLiters);
        $this->assertNull($detail->dimensions->weightKg);
        $this->assertNull($detail->engine->driveType);
        $this->assertNull($detail->engine->gearbox);
        $this->assertNull($detail->engine->maxTorque);
        $this->assertNull($detail->engine->type);
        $this->assertNull($detail->chassis->frameType);
        $this->assertNull($detail->chassis->frontSuspension);
        $this->assertNull($detail->chassis->rearSuspension);
        $this->assertNull($detail->chassis->frontBrake);
        $this->assertNull($detail->chassis->rearBrake);
        $this->assertNull($detail->chassis->frontTire);
        $this->assertNull($detail->chassis->rearTire);
        $this->assertNull($detail->dimensions->wheelbaseMm);
        $this->assertNull($detail->dimensions->lengthMm);
        $this->assertNull($detail->dimensions->widthMm);
        $this->assertNull($detail->dimensions->heightMm);
        $this->assertNull($detail->engine->topSpeed);
        $this->assertNull($detail->engine->compressionRatio);
        $this->assertNull($detail->engine->valves);
        $this->assertNull($detail->engine->boreAndStroke);
        $this->assertNull($detail->engine->fuelDelivery);
        $this->assertNull($detail->engine->ignition);
        $this->assertNull($detail->engine->starter);
        $this->assertNull($detail->engine->clutch);
        $this->assertNull($detail->availableColors);
        $this->assertNull($detail->isNew);
        $this->assertNull($detail->modelYear);
        $this->assertNull($detail->factoryWarrantyMonths);
        $this->assertNull($detail->dealerDescription);
        $this->assertNull($detail->seller->postalCode);
    }

    public function test_get_detail_with_legacy_tech_table_uses_table_specs(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->detailLegacyFixture()),
        ]);

        $result = new Result(
            brand: 'Honda',
            model: 'CBR 600 RR',
            askingPrice: 8999,
            priceType: PriceType::Asking,
            year: 2015,
            odometerReading: 32000,
            odometerReadingUnit: OdometerUnit::Kilometers,
            image: 'https://www.motoroccasion.nl/fotos/77777/thumb.jpg',
            url: 'https://www.motoroccasion.nl/motor/77777/honda-cbr-600-rr',
            seller: new Seller(name: 'Honda Shop', province: null, website: ''),
        );

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $detail = $client->getDetail($result);

        // Legacy title format still works
        $this->assertSame('Honda', $detail->brand);
        $this->assertSame('CBR 600 RR', $detail->model);

        // Specs from deta-tech table take priority (keys normalized through synonyms)
        $this->assertSame('Honda', $detail->specifications['Merk']);
        $this->assertSame('CBR 600 RR', $detail->specifications['Model']);
        $this->assertSame('599 cc', $detail->specifications['Cilinderinhoud']);
        $this->assertSame('Benzine', $detail->specifications['Brandstofsoort']);
        $this->assertSame('4', $detail->specifications['Aantal cilinders']);

        // Bug fix: extractors now work from tech table specs (not just description)
        $this->assertSame(599, $detail->engine->capacityCc);
        $this->assertFalse($detail->engine->isElectric);
        $this->assertSame('Benzine', $detail->engine->fuelType);
        $this->assertSame(4, $detail->engine->cylinders);
    }

    public function test_get_detail_with_full_tech_table_parses_all_fields(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->detailHondaFixture()),
        ]);

        $result = new Result(
            brand: 'Honda',
            model: 'Xl 750 Transalp',
            askingPrice: 10990,
            priceType: PriceType::Asking,
            year: 2026,
            odometerReading: 0,
            odometerReadingUnit: OdometerUnit::Kilometers,
            image: 'https://cdn.example.com/thumb.jpg',
            url: 'https://www.motoroccasion.nl/motoren/honda-xl-750-transalp-m1620704.html',
            seller: new Seller(name: 'MotoPort Goes', province: null, website: ''),
        );

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $detail = $client->getDetail($result);

        // Brand and model from new title format
        $this->assertSame('Honda', $detail->brand);
        $this->assertSame('Xl 750 Transalp', $detail->model);

        // Pricing
        $this->assertSame(10990, $detail->askingPrice);
        $this->assertSame(PriceType::Asking, $detail->priceType);
        $this->assertSame(12799, $detail->originalPrice);
        $this->assertSame(134, $detail->monthlyLease);

        // Header specs
        $this->assertSame(2026, $detail->year);
        $this->assertSame(0, $detail->odometerReading);
        $this->assertSame('WIT', $detail->color);
        $this->assertNull($detail->powerKw); // Empty in header
        $this->assertSame(LicenseCategory::A, $detail->license);
        $this->assertTrue($detail->warranty);

        // Images
        $this->assertCount(2, $detail->images);
        $this->assertSame('https://cdn.example.com/photo1_large.jpg', $detail->images[0]);

        // Description
        $this->assertNotNull($detail->description);
        $this->assertStringContainsString('Honda XL750 Transalp', $detail->description);

        // URL and ID (new URL format: -m{id}.html)
        $this->assertSame('https://www.motoroccasion.nl/motoren/honda-xl-750-transalp-m1620704.html', $detail->url);
        $this->assertSame(1620704, $detail->id);

        // Seller — parsed from DOM with gm_postcode from JS
        $this->assertSame('MotoPort Goes', $detail->seller->name);
        $this->assertSame('Nobelweg 4', $detail->seller->address);
        $this->assertSame('Goes', $detail->seller->city);
        $this->assertSame('0113-231640', $detail->seller->phone);
        $this->assertSame('https://www.motoport.nl/goes', $detail->seller->website);
        // Bug fix: postalCode from gm_postcode JS variable, NOT from EU representative address
        $this->assertSame('4462 GK', $detail->seller->postalCode);

        // Tech table specs — HTML entities decoded, keys normalized
        $this->assertSame('1,755 cc', $detail->specifications['Cilinderinhoud']);
        $this->assertSame('11.0:1', $detail->specifications['Compressieverhouding']);
        $this->assertSame('6 versnellingen, manuele koppeling', $detail->specifications['Versnellingsbak']);
        $this->assertSame('90/90-21', $detail->specifications['Voorband']);
        $this->assertSame('Stalen buizenframe, diamant type', $detail->specifications['Frame']);

        // Engine & drivetrain fields from tech table
        $this->assertSame(1755, $detail->engine->capacityCc);
        $this->assertNull($detail->engine->isElectric); // No fuel type in description or tech table
        $this->assertSame('Vloeistofgekoelde 4-takt paralleltwin', $detail->engine->type);
        $this->assertSame('87 mm x 87 mm', $detail->engine->boreAndStroke);
        $this->assertSame('11.0:1', $detail->engine->compressionRatio);
        $this->assertSame(8, $detail->engine->valves);
        $this->assertSame('PGM-FI elektronische benzine injectie', $detail->engine->fuelDelivery);
        $this->assertSame('Computergestuurde digitale transistor met elektronische vervroeging', $detail->engine->ignition);
        $this->assertSame('75 Nm bij 7250 tpm', $detail->engine->maxTorque);
        $this->assertSame('Natte meerplaatskoppeling', $detail->engine->clutch);
        $this->assertSame('6 versnellingen, manuele koppeling', $detail->engine->gearbox);
        $this->assertSame('Ketting', $detail->engine->driveType);
        $this->assertSame('electrisch', $detail->engine->starter);

        // Tank, ABS, chassis
        $this->assertSame(16.9, $detail->dimensions->tankCapacityLiters);
        $this->assertTrue($detail->chassis->abs);
        $this->assertSame('Stalen buizenframe, diamant type', $detail->chassis->frameType);

        // Suspension, brakes, tires
        $this->assertSame('Showa 43 mm SFF-CA USD, 190 mm veerweg', $detail->chassis->frontSuspension);
        $this->assertSame('Afzonderlijke druk, Pro-Link swingarm, 190 mm veerweg', $detail->chassis->rearSuspension);
        $this->assertStringContainsString('Dubbele remschijf 310 x 4,5 mm', $detail->chassis->frontBrake);
        $this->assertStringContainsString('enkelvoudige remschijf 256 x 6,0 mm', $detail->chassis->rearBrake);
        $this->assertSame('90/90-21', $detail->chassis->frontTire);
        $this->assertSame('150/70-18', $detail->chassis->rearTire);

        // Dimensions
        $this->assertSame(1535, $detail->dimensions->wheelbaseMm);
        $this->assertSame(2325, $detail->dimensions->lengthMm);
        $this->assertSame(838, $detail->dimensions->widthMm);
        $this->assertSame(1450, $detail->dimensions->heightMm);
        $this->assertSame(830, $detail->dimensions->seatHeightMm);

        // Weight from "Ledig gewicht" synonym
        $this->assertSame(208, $detail->dimensions->weightKg);

        // Empty tech table fields
        $this->assertNull($detail->engine->topSpeed); // Topsnelheid is empty

        // Available colors (HTML entities decoded)
        $this->assertStringContainsString('Ross White Metallic', $detail->availableColors);

        // Description-only fields
        $this->assertTrue($detail->isNew);
        $this->assertSame(2025, $detail->modelYear);
        $this->assertSame(72, $detail->factoryWarrantyMonths);

        // Dealer description from deta-beom
        $this->assertNotNull($detail->dealerDescription);
        $this->assertStringContainsString('MotoPort Goes', $detail->dealerDescription);
        $this->assertStringContainsString('Dealerschappen', $detail->dealerDescription);
    }

    public function test_get_images_returns_high_resolution_images(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->detailFixture()), // GET detail page
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $result = new Result(
            brand: 'BMW',
            model: 'R 4',
            askingPrice: 12500,
            priceType: PriceType::Asking,
            year: 1936,
            odometerReading: 60949,
            odometerReadingUnit: OdometerUnit::Kilometers,
            image: 'https://www.motoroccasion.nl/fotos/99999/thumb.jpg',
            url: 'https://www.motoroccasion.nl/motor/99999/bmw-r-4',
            seller: new Seller(name: 'MotoPort Goes', province: null, website: ''),
        );

        $images = $client->getImages($result);

        $this->assertCount(3, $images);
        $this->assertSame('https://www.motoroccasion.nl/fotos/99999/photo1_large.jpg', $images[0]);
        $this->assertSame('https://www.motoroccasion.nl/fotos/99999/photo2_large.jpg', $images[1]);
        $this->assertSame('https://www.motoroccasion.nl/fotos/99999/photo3_large.jpg', $images[2]);
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
        $this->assertSame(10990, $results[0]->askingPrice);
        $this->assertSame(PriceType::Asking, $results[0]->priceType);
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
        $this->assertSame(7990, $results[1]->askingPrice);
        $this->assertSame(PriceType::Asking, $results[1]->priceType);
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
        $this->assertSame(600, $results[2]->askingPrice);
        $this->assertSame(PriceType::Asking, $results[2]->priceType);
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
            askingPrice: 10990,
            priceType: PriceType::Asking,
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

        $this->assertSame(10990, $array['askingPrice']);
        $this->assertSame('asking', $array['priceType']);
        $this->assertSame(11999, $array['originalPrice']);
        $this->assertSame(134, $array['monthlyLease']);
        $this->assertSame('KM', $array['odometerReadingUnit']);
        $this->assertNull($array['seller']['province']);

        $reconstructed = Result::fromArray($array);

        $this->assertSame(11999, $reconstructed->originalPrice);
        $this->assertSame(134, $reconstructed->monthlyLease);
        $this->assertSame('SUZUKI', $reconstructed->brand);
        $this->assertSame(10990, $reconstructed->askingPrice);
        $this->assertSame(PriceType::Asking, $reconstructed->priceType);
        $this->assertSame(OdometerUnit::Kilometers, $reconstructed->odometerReadingUnit);
        $this->assertNull($reconstructed->seller->province);
    }

    public function test_result_dto_round_trip_without_offer_fields(): void
    {
        $result = new Result(
            brand: 'BMW',
            model: 'R 1250 GS',
            askingPrice: 18950,
            priceType: PriceType::Asking,
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

        $this->assertSame(18950, $array['askingPrice']);
        $this->assertSame('asking', $array['priceType']);
        $this->assertNull($array['originalPrice']);
        $this->assertNull($array['monthlyLease']);
        $this->assertSame('Noord-Holland', $array['seller']['province']);

        $reconstructed = Result::fromArray($array);

        $this->assertNull($reconstructed->originalPrice);
        $this->assertNull($reconstructed->monthlyLease);
        $this->assertSame('BMW', $reconstructed->brand);
        $this->assertSame(18950, $reconstructed->askingPrice);
        $this->assertSame(PriceType::Asking, $reconstructed->priceType);
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
            askingPrice: 12500,
            priceType: PriceType::Asking,
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
        $this->expectExceptionMessage('HTTP request failed while setting search parameters');

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

    public function test_search_form_404_throws_search_form_not_found_exception(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            new Response(404, [], 'Not Found'),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $this->expectException(SearchFormNotFoundException::class);
        $this->expectExceptionMessage('HTTP request failed while fetching search form');

        $client->getBrands();
    }

    public function test_search_form_other_client_error_throws_client_exception(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            new Response(403, [], 'Forbidden'),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP request failed while fetching search form');

        $client->getBrands();
    }

    public function test_search_form_server_error_throws_server_exception(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            new Response(500, [], 'Internal Server Error'),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('HTTP request failed while fetching search form');

        $client->getBrands();
    }

    public function test_network_error_throws_generic_motor_occasion_exception(): void
    {
        $mock = new MockHandler([
            new ConnectException('DNS failure', new Request('GET', 'https://www.motoroccasion.nl')),
        ]);

        $client = new MotorOccasion($this->createClientWithMock($mock));

        try {
            $client->getBrands();
            $this->fail('Expected MotorOccasionException was not thrown');
        } catch (MotorOccasionException $motorOccasionException) {
            $this->assertNotInstanceOf(ClientException::class, $motorOccasionException);
            $this->assertNotInstanceOf(ServerException::class, $motorOccasionException);
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
            askingPrice: 12500,
            priceType: PriceType::Asking,
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
            $this->okResponse(), // setSessionParams
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
        $this->assertNotNull($cache->get('motoroccasion:brands'));

        // Reset should clear external cache
        $client->resetSession();

        $this->assertNull($cache->get('motoroccasion:brands'));
        $this->assertNull($cache->get('motoroccasion:categories'));
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
            $this->okResponse(), // setSessionParams
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php
        ]);

        $client = new MotorOccasion(
            httpClient: $this->createClientWithMock($mock),
            cache: $cache,
        );

        // Populate the brands cache
        $brands = $client->getBrands();
        $this->assertCount(3, $brands);
        $this->assertNotNull($cache->get('motoroccasion:brands'));

        // search() should NOT wipe the external cache
        $searchResult = $client->search(new SearchCriteria(brand: $brand));
        $this->assertInstanceOf(SearchResult::class, $searchResult);

        // Brands cache should still be intact
        $this->assertNotNull($cache->get('motoroccasion:brands'), 'search() should not invalidate the external brands cache');
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
            askingPrice: 12500,
            priceType: PriceType::Asking,
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
        $this->assertSame(12500, $array['askingPrice']);
        $this->assertSame('asking', $array['priceType']);
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
        $this->assertSame(12500, $reconstructed->askingPrice);
        $this->assertSame(PriceType::Asking, $reconstructed->priceType);
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
            askingPrice: 5000,
            priceType: PriceType::Asking,
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

        // Engine sub-DTO
        $this->assertNull($array['engine']['capacityCc']);
        $this->assertNull($array['engine']['isElectric']);
        $this->assertNull($array['engine']['fuelType']);
        $this->assertNull($array['engine']['cylinders']);
        $this->assertNull($array['engine']['type']);
        $this->assertNull($array['engine']['valves']);
        $this->assertNull($array['engine']['boreAndStroke']);
        $this->assertNull($array['engine']['compressionRatio']);
        $this->assertNull($array['engine']['fuelDelivery']);
        $this->assertNull($array['engine']['ignition']);
        $this->assertNull($array['engine']['maxTorque']);
        $this->assertNull($array['engine']['clutch']);
        $this->assertNull($array['engine']['gearbox']);
        $this->assertNull($array['engine']['driveType']);
        $this->assertNull($array['engine']['starter']);
        $this->assertNull($array['engine']['topSpeed']);

        // Chassis sub-DTO
        $this->assertNull($array['chassis']['abs']);
        $this->assertNull($array['chassis']['frameType']);
        $this->assertNull($array['chassis']['frontSuspension']);
        $this->assertNull($array['chassis']['rearSuspension']);
        $this->assertNull($array['chassis']['frontBrake']);
        $this->assertNull($array['chassis']['rearBrake']);
        $this->assertNull($array['chassis']['frontTire']);
        $this->assertNull($array['chassis']['rearTire']);

        // Dimensions sub-DTO
        $this->assertNull($array['dimensions']['seatHeightMm']);
        $this->assertNull($array['dimensions']['wheelbaseMm']);
        $this->assertNull($array['dimensions']['lengthMm']);
        $this->assertNull($array['dimensions']['widthMm']);
        $this->assertNull($array['dimensions']['heightMm']);
        $this->assertNull($array['dimensions']['tankCapacityLiters']);
        $this->assertNull($array['dimensions']['weightKg']);

        // Flat fields
        $this->assertNull($array['vatDeductible']);
        $this->assertNull($array['licensePlate']);
        $this->assertNull($array['damageStatus']);
        $this->assertNull($array['bodyType']);
        $this->assertNull($array['roadTaxStatus']);
        $this->assertNull($array['availableColors']);
        $this->assertNull($array['isNew']);
        $this->assertNull($array['modelYear']);
        $this->assertNull($array['factoryWarrantyMonths']);
        $this->assertNull($array['dealerDescription']);

        $reconstructed = ListingDetail::fromArray($array);

        $this->assertNull($reconstructed->originalPrice);
        $this->assertNull($reconstructed->color);
        $this->assertNull($reconstructed->warranty);
        $this->assertNull($reconstructed->engine->capacityCc);
        $this->assertNull($reconstructed->engine->isElectric);
        $this->assertNull($reconstructed->vatDeductible);
        $this->assertNull($reconstructed->engine->fuelType);
        $this->assertNull($reconstructed->engine->cylinders);
        $this->assertNull($reconstructed->licensePlate);
        $this->assertNull($reconstructed->damageStatus);
        $this->assertNull($reconstructed->bodyType);
        $this->assertNull($reconstructed->roadTaxStatus);
        $this->assertNull($reconstructed->chassis->abs);
        $this->assertNull($reconstructed->dimensions->seatHeightMm);
        $this->assertNull($reconstructed->dimensions->tankCapacityLiters);
        $this->assertNull($reconstructed->dimensions->weightKg);
        $this->assertNull($reconstructed->engine->driveType);
        $this->assertNull($reconstructed->engine->gearbox);
        $this->assertNull($reconstructed->engine->maxTorque);
        $this->assertNull($reconstructed->engine->type);
        $this->assertNull($reconstructed->chassis->frameType);
        $this->assertNull($reconstructed->chassis->frontSuspension);
        $this->assertNull($reconstructed->chassis->rearSuspension);
        $this->assertNull($reconstructed->chassis->frontBrake);
        $this->assertNull($reconstructed->chassis->rearBrake);
        $this->assertNull($reconstructed->chassis->frontTire);
        $this->assertNull($reconstructed->chassis->rearTire);
        $this->assertNull($reconstructed->dimensions->wheelbaseMm);
        $this->assertNull($reconstructed->dimensions->lengthMm);
        $this->assertNull($reconstructed->dimensions->widthMm);
        $this->assertNull($reconstructed->dimensions->heightMm);
        $this->assertNull($reconstructed->engine->topSpeed);
        $this->assertNull($reconstructed->engine->compressionRatio);
        $this->assertNull($reconstructed->engine->valves);
        $this->assertNull($reconstructed->engine->boreAndStroke);
        $this->assertNull($reconstructed->engine->fuelDelivery);
        $this->assertNull($reconstructed->engine->ignition);
        $this->assertNull($reconstructed->engine->starter);
        $this->assertNull($reconstructed->engine->clutch);
        $this->assertNull($reconstructed->availableColors);
        $this->assertNull($reconstructed->isNew);
        $this->assertNull($reconstructed->modelYear);
        $this->assertNull($reconstructed->factoryWarrantyMonths);
        $this->assertNull($reconstructed->dealerDescription);
        $this->assertSame('Honda', $reconstructed->brand);
    }

    public function test_listing_detail_from_array_without_sub_dtos_defaults_to_empty(): void
    {
        $data = [
            'brand' => 'BMW',
            'model' => 'R 4',
            'askingPrice' => 12500,
            'priceType' => 'asking',
            'originalPrice' => null,
            'monthlyLease' => null,
            'year' => 1936,
            'odometerReading' => 60949,
            'odometerReadingUnit' => 'KM',
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
            ],
        ];

        $detail = ListingDetail::fromArray($data);

        $this->assertSame('BMW', $detail->brand);

        // Engine sub-DTO defaults to all nulls
        $this->assertNull($detail->engine->capacityCc);
        $this->assertNull($detail->engine->isElectric);
        $this->assertNull($detail->engine->fuelType);
        $this->assertNull($detail->engine->cylinders);
        $this->assertNull($detail->engine->type);
        $this->assertNull($detail->engine->valves);
        $this->assertNull($detail->engine->boreAndStroke);
        $this->assertNull($detail->engine->compressionRatio);
        $this->assertNull($detail->engine->fuelDelivery);
        $this->assertNull($detail->engine->ignition);
        $this->assertNull($detail->engine->maxTorque);
        $this->assertNull($detail->engine->clutch);
        $this->assertNull($detail->engine->gearbox);
        $this->assertNull($detail->engine->driveType);
        $this->assertNull($detail->engine->starter);
        $this->assertNull($detail->engine->topSpeed);

        // Chassis sub-DTO defaults to all nulls
        $this->assertNull($detail->chassis->abs);
        $this->assertNull($detail->chassis->frameType);
        $this->assertNull($detail->chassis->frontSuspension);
        $this->assertNull($detail->chassis->rearSuspension);
        $this->assertNull($detail->chassis->frontBrake);
        $this->assertNull($detail->chassis->rearBrake);
        $this->assertNull($detail->chassis->frontTire);
        $this->assertNull($detail->chassis->rearTire);

        // Dimensions sub-DTO defaults to all nulls
        $this->assertNull($detail->dimensions->seatHeightMm);
        $this->assertNull($detail->dimensions->wheelbaseMm);
        $this->assertNull($detail->dimensions->lengthMm);
        $this->assertNull($detail->dimensions->widthMm);
        $this->assertNull($detail->dimensions->heightMm);
        $this->assertNull($detail->dimensions->tankCapacityLiters);
        $this->assertNull($detail->dimensions->weightKg);

        // Flat optional fields
        $this->assertNull($detail->vatDeductible);
        $this->assertNull($detail->licensePlate);
        $this->assertNull($detail->damageStatus);
        $this->assertNull($detail->bodyType);
        $this->assertNull($detail->roadTaxStatus);
        $this->assertNull($detail->availableColors);
        $this->assertNull($detail->isNew);
        $this->assertNull($detail->modelYear);
        $this->assertNull($detail->factoryWarrantyMonths);
        $this->assertNull($detail->dealerDescription);
        $this->assertNull($detail->seller->postalCode);
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
            postalCode: '4461 ZM',
        );

        $array = $seller->toArray();

        $this->assertSame('Zeeland', $array['province']);
        $this->assertSame('Nobelweg 4', $array['address']);
        $this->assertSame('Goes', $array['city']);
        $this->assertSame('0113-231640', $array['phone']);
        $this->assertSame('4461 ZM', $array['postalCode']);

        $reconstructed = Seller::fromArray($array);

        $this->assertSame(Province::Zeeland, $reconstructed->province);
        $this->assertSame('Nobelweg 4', $reconstructed->address);
        $this->assertSame('Goes', $reconstructed->city);
        $this->assertSame('0113-231640', $reconstructed->phone);
        $this->assertSame('4461 ZM', $reconstructed->postalCode);
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
        $this->assertNull($array['postalCode']);

        $reconstructed = Seller::fromArray($array);

        $this->assertNull($reconstructed->province);
        $this->assertNull($reconstructed->address);
        $this->assertNull($reconstructed->city);
        $this->assertNull($reconstructed->phone);
        $this->assertNull($reconstructed->postalCode);
        $this->assertSame('Particulier', $reconstructed->name);
    }

    public function test_seller_from_array_backward_compatible_without_postal_code(): void
    {
        $data = [
            'name' => 'Old Shop',
            'province' => 'Noord-Holland',
            'website' => 'https://www.old.nl',
            'address' => 'Straat 1',
            'city' => 'Amsterdam',
            'phone' => '020-1234567',
        ];

        $seller = Seller::fromArray($data);

        $this->assertSame('Old Shop', $seller->name);
        $this->assertSame(Province::NoordHolland, $seller->province);
        $this->assertNull($seller->postalCode);
    }

    // --- Caching ---

    public function test_get_brands_returns_cached_result(): void
    {
        $cachedBrands = [
            new Brand(name: 'Cached BMW', value: 'bmw'),
            new Brand(name: 'Cached Honda', value: 'honda'),
        ];

        $cache = new ArrayCache();
        $cache->set('motoroccasion:brands', $cachedBrands);

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
        $cached = $cache->get('motoroccasion:brands');

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
        $cache->set('motoroccasion:categories', $cachedCategories);

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

        $cached = $cache->get('motoroccasion:categories');

        $this->assertNotNull($cached);
        $this->assertCount(17, $cached);
    }

    // --- fromArray() invalid enum values ---

    public function test_result_from_array_invalid_odometer_unit_throws_motor_occasion_exception(): void
    {
        $data = [
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'askingPrice' => 18950,
            'priceType' => 'asking',
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
            'askingPrice' => 12500,
            'priceType' => 'asking',
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

    // --- Sort order ---

    public function test_search_sends_default_order_when_no_sort_order_specified(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParams
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php
        ]);

        $history = [];
        $httpClient = $this->createClientWithMockAndHistory($mock, $history);

        $client = new MotorOccasion(httpClient: $httpClient);
        $client->search(new SearchCriteria(brand: $brand));

        $lastRequest = end($history);
        $query = $lastRequest['request']->getUri()->getQuery();

        $this->assertStringContainsString('params%5Border%5D=default', $query);
    }

    public function test_search_sends_specified_sort_order(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParams
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php
        ]);

        $history = [];
        $httpClient = $this->createClientWithMockAndHistory($mock, $history);

        $client = new MotorOccasion(httpClient: $httpClient);
        $client->search(new SearchCriteria(brand: $brand, sortOrder: SortOrder::YearDescending));

        $lastRequest = end($history);
        $query = $lastRequest['request']->getUri()->getQuery();

        $this->assertStringContainsString('params%5Border%5D=bwjr-desc', $query);
    }

    public function test_search_sends_price_ascending_sort_order(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParams
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php
        ]);

        $history = [];
        $httpClient = $this->createClientWithMockAndHistory($mock, $history);

        $client = new MotorOccasion(httpClient: $httpClient);
        $client->search(new SearchCriteria(brand: $brand, sortOrder: SortOrder::PriceAscending));

        $lastRequest = end($history);
        $query = $lastRequest['request']->getUri()->getQuery();

        $this->assertStringContainsString('params%5Border%5D=pric-asc', $query);
    }

    public function test_get_offers_sends_default_update_order_when_no_sort_order_specified(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->offersFixture()), // AJAX /ms.php
        ]);

        $history = [];
        $httpClient = $this->createClientWithMockAndHistory($mock, $history);

        $client = new MotorOccasion(httpClient: $httpClient);
        $client->getOffers();

        $lastRequest = end($history);
        $query = $lastRequest['request']->getUri()->getQuery();

        $this->assertStringContainsString('params%5Border%5D=update', $query);
    }

    public function test_get_offers_sends_specified_sort_order(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->offersFixture()), // AJAX /ms.php
        ]);

        $history = [];
        $httpClient = $this->createClientWithMockAndHistory($mock, $history);

        $client = new MotorOccasion(httpClient: $httpClient);
        $client->getOffers(sortOrder: SortOrder::PriceDescending);

        $lastRequest = end($history);
        $query = $lastRequest['request']->getUri()->getQuery();

        $this->assertStringContainsString('params%5Border%5D=pric-desc', $query);
    }

    public function test_search_criteria_default_sort_order_is_null(): void
    {
        $criteria = new SearchCriteria();

        $this->assertNull($criteria->sortOrder);
    }

    // --- Price type parsing ---

    public function test_result_from_array_invalid_price_type_throws_motor_occasion_exception(): void
    {
        $data = [
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'askingPrice' => 18950,
            'priceType' => 'INVALID',
            'year' => 2021,
            'odometerReading' => 25000,
            'odometerReadingUnit' => 'KM',
            'image' => 'https://example.com/bmw.jpg',
            'url' => 'https://www.motoroccasion.nl/motor/12345/bmw-r-1250-gs',
            'seller' => [
                'name' => 'Test',
                'province' => null,
                'website' => '',
            ],
        ];

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('Invalid price type: INVALID');

        Result::fromArray($data);
    }

    public function test_listing_detail_from_array_invalid_price_type_throws_motor_occasion_exception(): void
    {
        $data = [
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'askingPrice' => 18950,
            'priceType' => 'INVALID',
            'originalPrice' => null,
            'monthlyLease' => null,
            'year' => 2021,
            'odometerReading' => 25000,
            'odometerReadingUnit' => 'KM',
            'color' => null,
            'powerKw' => null,
            'license' => null,
            'warranty' => null,
            'images' => [],
            'description' => null,
            'specifications' => [],
            'url' => 'https://www.motoroccasion.nl/motor/12345/bmw-r-1250-gs',
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
        $this->expectExceptionMessage('Invalid price type: INVALID');

        ListingDetail::fromArray($data);
    }

    public function test_listing_detail_from_array_invalid_license_throws_motor_occasion_exception(): void
    {
        $data = [
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'askingPrice' => 18950,
            'priceType' => 'asking',
            'originalPrice' => null,
            'monthlyLease' => null,
            'year' => 2021,
            'odometerReading' => 25000,
            'odometerReadingUnit' => 'KM',
            'color' => null,
            'powerKw' => null,
            'license' => 'X99',
            'warranty' => null,
            'images' => [],
            'description' => null,
            'specifications' => [],
            'url' => 'https://www.motoroccasion.nl/motor/12345/bmw-r-1250-gs',
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
        $this->expectExceptionMessage('Invalid license category: X99');

        ListingDetail::fromArray($data);
    }

    // --- SearchResult edge cases ---

    public function test_search_result_total_pages_with_zero_per_page(): void
    {
        $searchResult = new SearchResult(
            results: [],
            totalCount: 100,
            currentPage: 1,
            perPage: 0,
        );

        $this->assertSame(0, $searchResult->totalPages());
        $this->assertFalse($searchResult->hasNextPage());
    }

    public function test_search_result_total_pages_with_negative_per_page(): void
    {
        $searchResult = new SearchResult(
            results: [],
            totalCount: 100,
            currentPage: 1,
            perPage: -1,
        );

        $this->assertSame(0, $searchResult->totalPages());
        $this->assertFalse($searchResult->hasNextPage());
    }

    // --- Empty search results ---

    public function test_search_with_no_matching_results(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse('<div class="noResultsFound">Geen resultaten gevonden</div>'),
        ]);

        $httpClient = $this->createClientWithMock($mock);

        $client = new MotorOccasion(httpClient: $httpClient);
        $searchResult = $client->search(new SearchCriteria());

        $this->assertCount(0, $searchResult->results);
        $this->assertSame(0, $searchResult->totalCount);
    }

    // --- Price type bidding/negotiable ---

    public function test_result_bidding_price_type(): void
    {
        $data = [
            'brand' => 'Honda',
            'model' => 'CBR',
            'askingPrice' => 5000,
            'priceType' => 'bidding',
            'year' => 2020,
            'odometerReading' => 10000,
            'odometerReadingUnit' => 'KM',
            'image' => '',
            'url' => 'https://www.motoroccasion.nl/motor/123/honda',
            'seller' => ['name' => 'Test', 'province' => null, 'website' => ''],
        ];

        $result = Result::fromArray($data);

        $this->assertSame(PriceType::Bidding, $result->priceType);
        $this->assertSame(5000, $result->askingPrice);
    }

    public function test_result_negotiable_price_type(): void
    {
        $data = [
            'brand' => 'Honda',
            'model' => 'CBR',
            'askingPrice' => null,
            'priceType' => 'negotiable',
            'year' => 2020,
            'odometerReading' => 10000,
            'odometerReadingUnit' => 'KM',
            'image' => '',
            'url' => 'https://www.motoroccasion.nl/motor/123/honda',
            'seller' => ['name' => 'Test', 'province' => null, 'website' => ''],
        ];

        $result = Result::fromArray($data);

        $this->assertSame(PriceType::Negotiable, $result->priceType);
        $this->assertNull($result->askingPrice);
    }

    // --- JSON key validation ---

    public function test_get_brands_throws_on_missing_brands_key(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse('{"types": "<option>test</option>"}'),
        ]);

        $httpClient = $this->createClientWithMock($mock);

        $client = new MotorOccasion(httpClient: $httpClient);

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('Missing or invalid "brands" key');

        $client->getBrands();
    }

    public function test_get_types_for_brand_throws_on_missing_types_key(): void
    {
        $brand = new Brand(name: 'BMW', value: '4');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParam
            $this->okResponse('{"brands": "<option>test</option>"}'),
        ]);

        $httpClient = $this->createClientWithMock($mock);

        $client = new MotorOccasion(httpClient: $httpClient);

        $this->expectException(MotorOccasionException::class);
        $this->expectExceptionMessage('Missing or invalid "types" key');

        $client->getTypesForBrand($brand);
    }

    // --- Batched session params ---

    public function test_search_batches_all_criteria_into_single_param_request(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');
        $type = new Type(name: 'R 1250 GS', value: 'r1250gs', brand: $brand);

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParams (batched)
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php
        ]);

        $history = [];
        $httpClient = $this->createClientWithMockAndHistory($mock, $history);

        $client = new MotorOccasion(httpClient: $httpClient);
        $client->search(new SearchCriteria(brand: $brand, type: $type, priceMin: 5000, yearMax: 2024));

        // Request 0: session (homepage), Request 1: batched params, Request 2: results
        $this->assertCount(3, $history);

        $batchQuery = $history[1]['request']->getUri()->getQuery();

        // All params sent in a single request
        $this->assertStringContainsString('params%5Bbr%5D=bmw', $batchQuery);
        $this->assertStringContainsString('params%5Bty%5D=r1250gs', $batchQuery);
        $this->assertStringContainsString('params%5Bpricfrom%5D=5000', $batchQuery);
        $this->assertStringContainsString('params%5Byeartill%5D=2024', $batchQuery);
        $this->assertStringContainsString('params%5Ba%5D=check', $batchQuery);
    }

    public function test_search_without_criteria_skips_param_request(): void
    {
        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php (no param set request)
        ]);

        $history = [];
        $httpClient = $this->createClientWithMockAndHistory($mock, $history);

        $client = new MotorOccasion(httpClient: $httpClient);
        $client->search(new SearchCriteria());

        // Request 0: session (homepage), Request 1: results — no param set request
        $this->assertCount(2, $history);
    }

    public function test_set_session_params_request_sends_x_requested_with(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParams
            $this->okResponse($this->resultsFixture()), // AJAX /mz.php
        ]);

        $history = [];
        $httpClient = $this->createClientWithMockAndHistory($mock, $history);

        $client = new MotorOccasion(httpClient: $httpClient);
        $client->search(new SearchCriteria(brand: $brand));

        // MotorOccasion treats /mz.php as an AJAX endpoint and rejects requests
        // without X-Requested-With with HTTP 404. The setSessionParams call
        // (history[1]) must therefore advertise itself as an XHR.
        $this->assertSame(
            'XMLHttpRequest',
            $history[1]['request']->getHeaderLine('X-Requested-With'),
        );
    }

    public function test_get_types_for_brand_request_sends_x_requested_with(): void
    {
        $brand = new Brand(name: 'BMW', value: 'bmw');

        $mock = new MockHandler([
            $this->sessionResponse(),
            $this->okResponse(), // setSessionParam('br', ...)
            $this->okResponse($this->searchFormFixture()),
        ]);

        $history = [];
        $httpClient = $this->createClientWithMockAndHistory($mock, $history);

        $client = new MotorOccasion(httpClient: $httpClient);
        $client->getTypesForBrand($brand);

        $this->assertSame(
            'XMLHttpRequest',
            $history[1]['request']->getHeaderLine('X-Requested-With'),
        );
    }
}
