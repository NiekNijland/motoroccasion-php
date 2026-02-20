<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Tests\Testing;

use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Category;
use NiekNijland\MotorOccasion\Data\Chassis;
use NiekNijland\MotorOccasion\Data\Dimensions;
use NiekNijland\MotorOccasion\Data\Engine;
use NiekNijland\MotorOccasion\Data\LicenseCategory;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\OdometerUnit;
use NiekNijland\MotorOccasion\Data\PriceType;
use NiekNijland\MotorOccasion\Data\Province;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\Seller;
use NiekNijland\MotorOccasion\Data\Type;
use NiekNijland\MotorOccasion\Testing\BrandFactory;
use NiekNijland\MotorOccasion\Testing\CategoryFactory;
use NiekNijland\MotorOccasion\Testing\ChassisFactory;
use NiekNijland\MotorOccasion\Testing\DimensionsFactory;
use NiekNijland\MotorOccasion\Testing\EngineFactory;
use NiekNijland\MotorOccasion\Testing\ListingDetailFactory;
use NiekNijland\MotorOccasion\Testing\ResultFactory;
use NiekNijland\MotorOccasion\Testing\SellerFactory;
use NiekNijland\MotorOccasion\Testing\TypeFactory;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase
{
    // --- BrandFactory ---

    public function test_brand_factory_creates_default_brand(): void
    {
        $brand = BrandFactory::make();

        $this->assertInstanceOf(Brand::class, $brand);
        $this->assertSame('BMW', $brand->name);
        $this->assertSame('bmw', $brand->value);
    }

    public function test_brand_factory_accepts_overrides(): void
    {
        $brand = BrandFactory::make(name: 'Honda', value: 'honda');

        $this->assertSame('Honda', $brand->name);
        $this->assertSame('honda', $brand->value);
    }

    public function test_brand_factory_make_many(): void
    {
        $brands = BrandFactory::makeMany(5);

        $this->assertCount(5, $brands);

        foreach ($brands as $brand) {
            $this->assertInstanceOf(Brand::class, $brand);
            $this->assertNotEmpty($brand->name);
            $this->assertNotEmpty($brand->value);
        }

        // First 3 should be unique
        $names = array_map(fn (Brand $b): string => $b->name, $brands);
        $this->assertSame('BMW', $names[0]);
        $this->assertSame('Honda', $names[1]);
        $this->assertSame('Yamaha', $names[2]);
    }

    public function test_brand_factory_make_many_wraps_around(): void
    {
        $brands = BrandFactory::makeMany(12);

        $this->assertCount(12, $brands);
        // Index 10 wraps to index 0
        $this->assertSame($brands[0]->name, $brands[10]->name);
    }

    // --- CategoryFactory ---

    public function test_category_factory_creates_default_category(): void
    {
        $category = CategoryFactory::make();

        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame('Naked', $category->name);
        $this->assertSame('43', $category->value);
    }

    public function test_category_factory_accepts_overrides(): void
    {
        $category = CategoryFactory::make(name: 'Sport', value: '4');

        $this->assertSame('Sport', $category->name);
        $this->assertSame('4', $category->value);
    }

    public function test_category_factory_make_many(): void
    {
        $categories = CategoryFactory::makeMany(4);

        $this->assertCount(4, $categories);

        foreach ($categories as $category) {
            $this->assertInstanceOf(Category::class, $category);
        }
    }

    // --- TypeFactory ---

    public function test_type_factory_creates_default_type(): void
    {
        $type = TypeFactory::make();

        $this->assertInstanceOf(Type::class, $type);
        $this->assertSame('R 1250 GS', $type->name);
        $this->assertSame('r1250gs', $type->value);
        $this->assertInstanceOf(Brand::class, $type->brand);
        $this->assertSame('BMW', $type->brand->name);
    }

    public function test_type_factory_accepts_brand_override(): void
    {
        $brand = BrandFactory::make(name: 'Honda', value: 'honda');
        $type = TypeFactory::make(name: 'CB650R', value: 'cb650r', brand: $brand);

        $this->assertSame('CB650R', $type->name);
        $this->assertSame($brand, $type->brand);
    }

    public function test_type_factory_make_many_shares_brand(): void
    {
        $brand = BrandFactory::make(name: 'Yamaha', value: 'yamaha');
        $types = TypeFactory::makeMany(3, brand: $brand);

        $this->assertCount(3, $types);

        foreach ($types as $type) {
            $this->assertSame($brand, $type->brand);
        }
    }

    // --- SellerFactory ---

    public function test_seller_factory_creates_default_seller(): void
    {
        $seller = SellerFactory::make();

        $this->assertInstanceOf(Seller::class, $seller);
        $this->assertSame('De Motor Shop', $seller->name);
        $this->assertSame(Province::NoordHolland, $seller->province);
        $this->assertSame('https://www.example.nl', $seller->website);
        $this->assertNull($seller->address);
    }

    public function test_seller_factory_creates_dealer_with_full_info(): void
    {
        $seller = SellerFactory::makeDealer();

        $this->assertSame('MotoPort Goes', $seller->name);
        $this->assertNull($seller->province);
        $this->assertSame('Nobelweg 4', $seller->address);
        $this->assertSame('Goes', $seller->city);
        $this->assertSame('0113-231640', $seller->phone);
    }

    public function test_seller_factory_creates_private_seller(): void
    {
        $seller = SellerFactory::makePrivate();

        $this->assertSame('Particulier', $seller->name);
        $this->assertSame(Province::ZuidHolland, $seller->province);
        $this->assertSame('', $seller->website);
        $this->assertNull($seller->address);
    }

    public function test_seller_factory_accepts_overrides(): void
    {
        $seller = SellerFactory::make(
            name: 'Custom Shop',
            province: Province::Utrecht,
            address: 'Hoofdstraat 1',
            city: 'Utrecht',
        );

        $this->assertSame('Custom Shop', $seller->name);
        $this->assertSame(Province::Utrecht, $seller->province);
        $this->assertSame('Hoofdstraat 1', $seller->address);
        $this->assertSame('Utrecht', $seller->city);
    }

    // --- ResultFactory ---

    public function test_result_factory_creates_default_result(): void
    {
        $result = ResultFactory::make();

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame('BMW', $result->brand);
        $this->assertSame('R 1250 GS', $result->model);
        $this->assertSame(18950, $result->askingPrice);
        $this->assertSame(PriceType::Asking, $result->priceType);
        $this->assertSame(2021, $result->year);
        $this->assertSame(25000, $result->odometerReading);
        $this->assertSame(OdometerUnit::Kilometers, $result->odometerReadingUnit);
        $this->assertSame(12345, $result->id);
        $this->assertInstanceOf(Seller::class, $result->seller);
        $this->assertSame(['https://www.motoroccasion.nl/fotos/12345/thumb.jpg'], $result->images);
    }

    public function test_result_factory_accepts_overrides(): void
    {
        $result = ResultFactory::make(
            brand: 'Honda',
            model: 'CB650R',
            askingPrice: 8200,
            originalPrice: 9000,
        );

        $this->assertSame('Honda', $result->brand);
        $this->assertSame('CB650R', $result->model);
        $this->assertSame(8200, $result->askingPrice);
        $this->assertSame(PriceType::Asking, $result->priceType);
        $this->assertSame(9000, $result->originalPrice);
    }

    public function test_result_factory_accepts_custom_seller(): void
    {
        $seller = SellerFactory::makePrivate();
        $result = ResultFactory::make(seller: $seller);

        $this->assertSame($seller, $result->seller);
        $this->assertSame('Particulier', $result->seller->name);
    }

    public function test_result_factory_make_many(): void
    {
        $results = ResultFactory::makeMany(5);

        $this->assertCount(5, $results);

        $brands = array_map(fn (Result $r): string => $r->brand, $results);
        $this->assertSame('BMW', $brands[0]);
        $this->assertSame('Yamaha', $brands[1]);
        $this->assertSame('Honda', $brands[2]);

        foreach ($results as $result) {
            $this->assertNotNull($result->id);
            $this->assertNotEmpty($result->url);
        }
    }

    // --- ListingDetailFactory ---

    public function test_listing_detail_factory_creates_default_detail(): void
    {
        $detail = ListingDetailFactory::make();

        $this->assertInstanceOf(ListingDetail::class, $detail);
        $this->assertSame('BMW', $detail->brand);
        $this->assertSame('R 1250 GS', $detail->model);
        $this->assertSame(18950, $detail->askingPrice);
        $this->assertSame(PriceType::Asking, $detail->priceType);
        $this->assertSame(2021, $detail->year);
        $this->assertSame(OdometerUnit::Kilometers, $detail->odometerReadingUnit);
        $this->assertSame('ZWART', $detail->color);
        $this->assertSame(100, $detail->powerKw);
        $this->assertSame(LicenseCategory::A, $detail->license);
        $this->assertTrue($detail->warranty);
        $this->assertSame(12345, $detail->id);
        $this->assertCount(2, $detail->images);
        $this->assertCount(3, $detail->specifications);
        $this->assertInstanceOf(Seller::class, $detail->seller);
    }

    public function test_listing_detail_factory_accepts_overrides(): void
    {
        $detail = ListingDetailFactory::make(
            brand: 'Honda',
            model: 'CB500F',
            askingPrice: 5000,
            color: null,
            license: LicenseCategory::A2,
            warranty: false,
        );

        $this->assertSame('Honda', $detail->brand);
        $this->assertSame(5000, $detail->askingPrice);
        $this->assertSame(PriceType::Asking, $detail->priceType);
        $this->assertNull($detail->color);
        $this->assertSame(LicenseCategory::A2, $detail->license);
        $this->assertFalse($detail->warranty);
    }

    public function test_listing_detail_factory_custom_images_and_specs(): void
    {
        $images = ['https://example.com/photo1.jpg'];
        $specs = ['Merk' => 'KTM'];

        $detail = ListingDetailFactory::make(images: $images, specifications: $specs);

        $this->assertSame($images, $detail->images);
        $this->assertSame($specs, $detail->specifications);
    }

    public function test_listing_detail_factory_round_trips_through_serialization(): void
    {
        $detail = ListingDetailFactory::make();

        $array = $detail->toArray();
        $restored = ListingDetail::fromArray($array);

        $this->assertSame($detail->brand, $restored->brand);
        $this->assertSame($detail->askingPrice, $restored->askingPrice);
        $this->assertSame($detail->priceType, $restored->priceType);
        $this->assertSame($detail->id, $restored->id);
        $this->assertSame($detail->license, $restored->license);
        $this->assertSame($detail->odometerReadingUnit, $restored->odometerReadingUnit);
    }

    public function test_listing_detail_factory_accepts_sub_dto_overrides(): void
    {
        $engine = EngineFactory::make(capacityCc: 1254, cylinders: 4);
        $chassis = ChassisFactory::make(abs: true, frameType: 'Aluminium');
        $dimensions = DimensionsFactory::make(seatHeightMm: 850, weightKg: 249);

        $detail = ListingDetailFactory::make(
            engine: $engine,
            chassis: $chassis,
            dimensions: $dimensions,
        );

        $this->assertSame(1254, $detail->engine->capacityCc);
        $this->assertSame(4, $detail->engine->cylinders);
        $this->assertTrue($detail->chassis->abs);
        $this->assertSame('Aluminium', $detail->chassis->frameType);
        $this->assertSame(249, $detail->dimensions->weightKg);
        $this->assertSame(850, $detail->dimensions->seatHeightMm);
    }

    // --- EngineFactory ---

    public function test_engine_factory_creates_default_engine(): void
    {
        $engine = EngineFactory::make();

        $this->assertInstanceOf(Engine::class, $engine);
        $this->assertNull($engine->capacityCc);
        $this->assertNull($engine->type);
        $this->assertNull($engine->cylinders);
    }

    public function test_engine_factory_accepts_overrides(): void
    {
        $engine = EngineFactory::make(
            capacityCc: 755,
            type: 'Paralleltwin',
            cylinders: 2,
            fuelType: 'Benzine',
            isElectric: false,
        );

        $this->assertSame(755, $engine->capacityCc);
        $this->assertSame('Paralleltwin', $engine->type);
        $this->assertSame(2, $engine->cylinders);
        $this->assertSame('Benzine', $engine->fuelType);
        $this->assertFalse($engine->isElectric);
    }

    // --- ChassisFactory ---

    public function test_chassis_factory_creates_default_chassis(): void
    {
        $chassis = ChassisFactory::make();

        $this->assertInstanceOf(Chassis::class, $chassis);
        $this->assertNull($chassis->abs);
        $this->assertNull($chassis->frameType);
    }

    public function test_chassis_factory_accepts_overrides(): void
    {
        $chassis = ChassisFactory::make(
            abs: true,
            frameType: 'Stalen buizenframe',
            frontTire: '120/70-17',
            rearTire: '180/55-17',
        );

        $this->assertTrue($chassis->abs);
        $this->assertSame('Stalen buizenframe', $chassis->frameType);
        $this->assertSame('120/70-17', $chassis->frontTire);
        $this->assertSame('180/55-17', $chassis->rearTire);
    }

    // --- DimensionsFactory ---

    public function test_dimensions_factory_creates_default_dimensions(): void
    {
        $dimensions = DimensionsFactory::make();

        $this->assertInstanceOf(Dimensions::class, $dimensions);
        $this->assertNull($dimensions->seatHeightMm);
        $this->assertNull($dimensions->weightKg);
    }

    public function test_dimensions_factory_accepts_overrides(): void
    {
        $dimensions = DimensionsFactory::make(
            seatHeightMm: 830,
            wheelbaseMm: 1535,
            tankCapacityLiters: 16.9,
            weightKg: 208,
        );

        $this->assertSame(830, $dimensions->seatHeightMm);
        $this->assertSame(1535, $dimensions->wheelbaseMm);
        $this->assertSame(16.9, $dimensions->tankCapacityLiters);
        $this->assertSame(208, $dimensions->weightKg);
    }
}
