# motoroccasion-php

PHP client for [motoroccasion.nl](https://www.motoroccasion.nl) -- the largest used motorcycle marketplace in the Netherlands. Search listings, browse brands and types, view special dealer offers, and fetch full listing details with photos and specifications.

## Requirements

- PHP 8.3+
- `ext-dom`

## Installation

```bash
composer require nieknijland/motoroccasion-php
```

## Quick start

```php
use NiekNijland\MotorOccasion\MotorOccasion;
use NiekNijland\MotorOccasion\Data\SearchCriteria;

$client = new MotorOccasion();

// Find all Yamaha MT-07 listings
$brands = $client->getBrands();
$yamaha = $brands[3]; // YAMAHA

$types = $client->getTypesForBrand($yamaha);
$mt07 = $types[12]; // MT 07

$results = $client->search(new SearchCriteria(brand: $yamaha, type: $mt07));

echo "{$results->totalCount} motorcycles found\n";

foreach ($results->results as $listing) {
    echo "{$listing->brand} {$listing->model} - EUR {$listing->askingPrice} ({$listing->year})\n";
    // BMW R 1250 GS Adventure - EUR 18950 (2021)
}
```

## Usage

### Brands and types

Brands and types are the two-level hierarchy used by motoroccasion.nl. A brand is e.g. "BMW", and a type is e.g. "R 1250 GS". Both carry a `name` (display label) and `value` (internal ID used for searching).

```php
$brands = $client->getBrands();
// [Brand(name: 'APRILIA', value: '1'), Brand(name: 'BMW', value: '4'), ...]

$types = $client->getTypesForBrand($brands[1]); // BMW
// [Type(name: 'C 1', value: '434'), Type(name: 'R 1250 GS', value: 'g911'), ...]

// Each type carries a reference back to its brand
echo $types[0]->brand->name; // "BMW"
```

### Categories

Categories group motorcycles by style (Naked, Toer, Sport, etc.) and are parsed from the homepage.

```php
$categories = $client->getCategories();
// [Category(name: 'All Off Road', value: '1'), Category(name: 'Naked', value: '43'), ...]
```

### Searching

Pass a `SearchCriteria` object to `search()`. All filter fields are optional -- omit any you don't need.

```php
use NiekNijland\MotorOccasion\Data\SearchCriteria;

// Simple: just a brand
$results = $client->search(new SearchCriteria(brand: $yamaha));

// Narrow it down: brand + type + price range + recent years
$results = $client->search(new SearchCriteria(
    brand: $yamaha,
    type: $mt07,
    priceMin: 4000,
    priceMax: 8000,
    yearMin: 2018,
));

// Filter by category, engine size, license type, etc.
$results = $client->search(new SearchCriteria(
    category: $categories[6],      // Naked
    engineCapacityMin: 600,
    engineCapacityMax: 900,
    license: LicenseCategory::A2,
    odometerMax: 30000,
));

// Search near a postal code
$results = $client->search(new SearchCriteria(
    brand: $yamaha,
    postalCode: '1012AB',
    radius: 50,
));

// Free-text keyword search
$results = $client->search(new SearchCriteria(
    keywords: 'quickshifter',
));

// Sort by price (low to high)
$results = $client->search(new SearchCriteria(
    brand: $yamaha,
    sortOrder: SortOrder::PriceAscending,
));

// Sort by year (newest first)
$results = $client->search(new SearchCriteria(
    brand: $yamaha,
    sortOrder: SortOrder::YearDescending,
));
```

All available `SearchCriteria` fields:

| Field | Type | Description |
|---|---|---|
| `brand` | `?Brand` | Filter by brand |
| `type` | `?Type` | Filter by type (model) |
| `category` | `?Category` | Filter by category (Naked, Sport, etc.) |
| `priceMin` / `priceMax` | `?int` | Price range in EUR |
| `yearMin` / `yearMax` | `?int` | Build year range |
| `odometerMin` / `odometerMax` | `?int` | Mileage range |
| `engineCapacityMin` / `engineCapacityMax` | `?int` | Engine displacement in cc |
| `powerMin` / `powerMax` | `?int` | Power in kW |
| `license` | `?LicenseCategory` | License type (A, A1, A2, AM, B) |
| `electric` | `?bool` | Electric motorcycles only |
| `vatDeductible` | `?bool` | VAT-deductible (BTW) only |
| `postalCode` | `?string` | Dutch postal code for proximity search |
| `radius` | `?int` | Radius in km (used with `postalCode`) |
| `keywords` | `?string` | Free-text search |
| `selection` | `?string` | Selection filter |
| `sortOrder` | `?SortOrder` | Sort order (default: relevance) |
| `page` | `int` | Page number (default: 1) |
| `perPage` | `int` | Results per page (default: 50, max: 50) |

### Search results and pagination

`search()` returns a `SearchResult` with the listings and pagination metadata.

```php
$results = $client->search(new SearchCriteria(brand: $yamaha));

echo $results->totalCount;  // 1842
echo $results->currentPage;  // 1
echo $results->perPage;      // 50
echo $results->totalPages();  // 37
echo $results->hasNextPage(); // true

// Fetch page 2
$page2 = $client->search(new SearchCriteria(brand: $yamaha, page: 2));

// Use a smaller page size
$page1 = $client->search(new SearchCriteria(brand: $yamaha, perPage: 10));
```

Each result in the array is a `Result` object:

```php
foreach ($results->results as $listing) {
    $listing->id;                  // 12345 (numeric listing ID, or null)
    $listing->brand;               // "YAMAHA"
    $listing->model;               // "MT 07"
    $listing->askingPrice;         // 6450 (EUR, or null for "prijs op aanvraag")
    $listing->priceType;           // PriceType::Asking (Asking, OnRequest, Negotiable, Bidding)
    $listing->year;                // 2019
    $listing->odometerReading;     // 23500
    $listing->odometerReadingUnit; // OdometerUnit::Kilometers
    $listing->image;               // "https://www.motoroccasion.nl/fotos/..."
    $listing->url;                 // "/motor/12345/yamaha-mt-07"
    $listing->originalPrice;       // 7200 (or null)
    $listing->monthlyLease;        // 134 (EUR/month, or null)
    $listing->seller->name;        // "Goedhart Motoren"
    $listing->seller->province;    // Province::ZuidHolland (or null)
    $listing->seller->website;     // "https://www.goedhart.nl"
}
```

### Listing details

Fetch the full detail page for a listing. This provides additional info like color, power, photos, technical specs, description, and dealer contact details.

```php
$detail = $client->getDetail($listing);

$detail->id;               // 12345 (numeric listing ID, or null)
$detail->brand;            // "YAMAHA"
$detail->model;            // "MT 07"
$detail->askingPrice;      // 6450 (EUR, or null for "prijs op aanvraag")
$detail->priceType;        // PriceType::Asking
$detail->originalPrice;    // 7200 (or null)
$detail->monthlyLease;     // 134 (EUR/month, or null)
$detail->year;             // 2019
$detail->odometerReading;  // 23500
$detail->odometerReadingUnit; // OdometerUnit::Kilometers
$detail->color;            // "ZWART"
$detail->powerKw;          // 55 (kW, or null)
$detail->license;          // LicenseCategory::A2 (or null)
$detail->warranty;         // true (or null)
$detail->images;           // ["https://...photo1.jpg", "https://...photo2.jpg", ...]
$detail->description;      // "Mooie MT-07 in perfecte staat..."
$detail->specifications;   // ["Merk" => "YAMAHA", "Model" => "MT 07", "Cilinderinhoud" => "689 cc", ...]
$detail->url;              // "/motor/12345/yamaha-mt-07"

// Engine sub-DTO (all fields nullable)
$detail->engine->capacityCc;      // 755 (engine displacement in cc)
$detail->engine->type;            // "Vloeistofgekoelde 4-takt paralleltwin"
$detail->engine->cylinders;       // 2
$detail->engine->valves;          // 8
$detail->engine->boreAndStroke;   // "87 mm x 87 mm"
$detail->engine->compressionRatio; // "11.0:1"
$detail->engine->fuelDelivery;    // "PGM-FI elektronische benzine injectie"
$detail->engine->fuelType;        // "Benzine"
$detail->engine->isElectric;      // false
$detail->engine->ignition;        // "Computergestuurde digitale transistor..."
$detail->engine->maxTorque;       // "75 Nm bij 7250 tpm"
$detail->engine->clutch;          // "Natte meerplaatskoppeling"
$detail->engine->gearbox;         // "6 versnellingen, manuele koppeling"
$detail->engine->driveType;       // "Ketting" (Ketting/Kardan/Riem)
$detail->engine->starter;         // "electrisch"
$detail->engine->topSpeed;        // "200 km/h"

// Chassis sub-DTO (all fields nullable)
$detail->chassis->abs;             // true
$detail->chassis->frameType;       // "Stalen buizenframe, diamant type"
$detail->chassis->frontSuspension; // "Showa 43 mm SFF-CA USD, 190 mm veerweg"
$detail->chassis->rearSuspension;  // "Pro-Link swingarm, 190 mm veerweg"
$detail->chassis->frontBrake;      // "Dubbele remschijf 310 x 4,5 mm..."
$detail->chassis->rearBrake;       // "enkelvoudige remschijf 256 x 6,0 mm..."
$detail->chassis->frontTire;       // "90/90-21"
$detail->chassis->rearTire;        // "150/70-18"

// Dimensions sub-DTO (all fields nullable)
$detail->dimensions->seatHeightMm;     // 830 (mm)
$detail->dimensions->wheelbaseMm;      // 1535 (mm)
$detail->dimensions->lengthMm;         // 2325 (mm)
$detail->dimensions->widthMm;          // 838 (mm)
$detail->dimensions->heightMm;         // 1450 (mm)
$detail->dimensions->tankCapacityLiters; // 16.9 (liters)
$detail->dimensions->weightKg;         // 208 (dry weight in kg)

// Listing-level fields (all nullable)
$detail->vatDeductible;          // false (VAT deductible)
$detail->licensePlate;           // "AB-123-CD" (Dutch license plate)
$detail->damageStatus;           // "schadevrij"
$detail->bodyType;               // "Naked"
$detail->roadTaxStatus;          // "Vrijgesteld"
$detail->availableColors;        // "Ross White Metallic, Mat Ballistic Black"
$detail->isNew;                  // true
$detail->modelYear;              // 2025 (distinct from build year)
$detail->factoryWarrantyMonths;  // 72 (factory warranty in months)
$detail->dealerDescription;      // "Voor kwaliteit en betrouwbaarheid..."

// Seller details
$detail->seller->name;       // "MotoPort Goes"
$detail->seller->website;    // "https://www.motoport.nl/goes"
$detail->seller->address;    // "Nobelweg 4"
$detail->seller->city;       // "Goes"
$detail->seller->phone;      // "0113-231640"
$detail->seller->postalCode; // "4462 GK" (Dutch postal code, or null)
```

Structured fields are extracted from both the technical specifications table and key-value pairs in descriptions. Data from both sources is merged -- description values take precedence over tech table values for the same field. Not all listings contain these details -- when absent, fields are `null`. The raw `specifications` array and `description` remain available as fallback.

### Special offers

Fetch dealer offers (discounted listings). These are paginated separately with 40 results per page by default.

```php
$offers = $client->getOffers();

echo $offers->totalCount; // 1334
echo $offers->results[0]->originalPrice; // 11999 (was-price)
echo $offers->results[0]->monthlyLease;  // 134 (EUR/month)

// Paginate offers
$page2 = $client->getOffers(page: 2);
$page1 = $client->getOffers(page: 1, perPage: 20);

// Sort offers by price (default: recently updated)
$offers = $client->getOffers(sortOrder: SortOrder::PriceDescending);
```

### Province enum

Seller locations are mapped to a `Province` enum. The site uses variable-length abbreviations (e.g. "NH", "Z-H", "GLD") which are automatically resolved.

```php
use NiekNijland\MotorOccasion\Data\Province;

$listing->seller->province;          // Province::NoordHolland
$listing->seller->province->value;   // "Noord-Holland"

// Manual lookup
Province::tryFromAbbreviation('NH');  // Province::NoordHolland
Province::tryFromAbbreviation('Z-H'); // Province::ZuidHolland
Province::tryFromAbbreviation('GLD'); // Province::Gelderland
```

### OdometerUnit enum

Odometer readings use the `OdometerUnit` enum instead of raw strings.

```php
use NiekNijland\MotorOccasion\Data\OdometerUnit;

$listing->odometerReadingUnit;          // OdometerUnit::Kilometers
$listing->odometerReadingUnit->value;   // "KM"

// Available cases
OdometerUnit::Kilometers; // "KM"
OdometerUnit::Miles;      // "MI"
```

### LicenseCategory enum

Dutch motorcycle license categories are represented as a `LicenseCategory` enum.

```php
use NiekNijland\MotorOccasion\Data\LicenseCategory;

$detail->license;          // LicenseCategory::A2
$detail->license->value;   // "A2"

// Available cases
LicenseCategory::A;   // "A"  - full motorcycle license
LicenseCategory::A1;  // "A1" - light motorcycles (max 125cc / 11kW)
LicenseCategory::A2;  // "A2" - medium motorcycles (max 35kW)
LicenseCategory::AM;  // "AM" - mopeds
LicenseCategory::B;   // "B"  - trikes/quads (car license)

// Use in search criteria
$results = $client->search(new SearchCriteria(license: LicenseCategory::A2));
```

### PriceType enum

Listings can have different pricing models. The `PriceType` enum indicates how to interpret the `askingPrice` field.

```php
use NiekNijland\MotorOccasion\Data\PriceType;

$listing->priceType;          // PriceType::Asking
$listing->priceType->value;   // "asking"

// Available cases
PriceType::Asking;      // "asking"     - regular asking price (askingPrice is set)
PriceType::OnRequest;   // "on_request" - "prijs op aanvraag" (askingPrice is null)
PriceType::Negotiable;  // "negotiable" - "n.o.t.k." (askingPrice is null)
PriceType::Bidding;     // "bidding"    - open for bids (askingPrice is null)

// Handle different price types
match ($listing->priceType) {
    PriceType::Asking     => "€ {$listing->askingPrice}",
    PriceType::OnRequest  => 'Prijs op aanvraag',
    PriceType::Negotiable => 'Nader overeen te komen',
    PriceType::Bidding    => 'Bieden',
};
```

### SortOrder enum

Control the order of search results and offers with the `SortOrder` enum.

```php
use NiekNijland\MotorOccasion\Data\SortOrder;

// Available cases
SortOrder::Default;          // "default"   - relevance (search default)
SortOrder::RecentlyUpdated;  // "update"    - most recently updated (offers default)
SortOrder::BrandAscending;   // "merk-asc"  - brand + type A-Z
SortOrder::BrandDescending;  // "merk-desc" - brand + type Z-A
SortOrder::YearAscending;    // "bwjr-asc"  - year old to new
SortOrder::YearDescending;   // "bwjr-desc" - year new to old
SortOrder::PriceAscending;   // "pric-asc"  - price low to high
SortOrder::PriceDescending;  // "pric-desc" - price high to low

// Use in search
$results = $client->search(new SearchCriteria(
    brand: $yamaha,
    sortOrder: SortOrder::PriceAscending,
));

// Use in offers
$offers = $client->getOffers(sortOrder: SortOrder::YearDescending);
```

### Serialization

All DTOs support `toArray()` and `fromArray()` for serialization (e.g. caching or storing in a database). Enum values are serialized as their string backing values.

```php
$array = $listing->toArray();
// ['brand' => 'YAMAHA', 'model' => 'MT 07', 'askingPrice' => 6450, 'priceType' => 'asking', 'odometerReadingUnit' => 'KM', 'seller' => ['name' => '...', ...], ...]

$restored = Result::fromArray($array);

// Also works for Brand, Category, Type, Seller, ListingDetail
$brand->toArray();                   // ['name' => 'BMW', 'value' => 'bmw']
Brand::fromArray($brandArray);
```

### Caching

Pass any PSR-16 (`psr/simple-cache`) implementation to cache brands and categories, avoiding repeated HTTP requests.

```php
use NiekNijland\MotorOccasion\MotorOccasion;

$client = new MotorOccasion(
    cache: $yourPsr16Cache,
    cacheTtl: 3600, // seconds (default: 1 hour)
);

$brands = $client->getBrands();      // fetched from site, stored in cache
$brands = $client->getBrands();      // returned from cache (no HTTP)
$categories = $client->getCategories(); // also cached
```

Calling `resetSession()` invalidates both in-memory and external cache entries.

### Session management

The client automatically manages PHP session cookies required by motoroccasion.nl. Sessions are established on the first API call and reused for subsequent calls. To force a fresh session:

```php
$client->resetSession();
```

### Interface

The client implements `MotorOccasionInterface`, which can be used for type-hinting and mocking in tests.

```php
use NiekNijland\MotorOccasion\MotorOccasionInterface;

public function __construct(private MotorOccasionInterface $client) {}
```

### Error handling

All public methods throw `MotorOccasionException` on HTTP failures or unexpected HTML structures.

```php
use NiekNijland\MotorOccasion\Exception\MotorOccasionException;

try {
    $results = $client->search(new SearchCriteria(brand: $brand));
} catch (MotorOccasionException $e) {
    // "Could not fetch AJAX results from /mz.php"
    // "Could not retrieve session from motoroccasion.nl"
    echo $e->getMessage();
}
```

## Testing

```bash
composer test
```

### Testing your application

This package ships with a `FakeMotorOccasion` client and DTO factories in `NiekNijland\MotorOccasion\Testing\` to make it easy to test code that depends on this package — no HTTP mocking required.

#### FakeMotorOccasion

A drop-in replacement for the real client. Seed it with data and use it via dependency injection.

```php
use NiekNijland\MotorOccasion\Testing\FakeMotorOccasion;
use NiekNijland\MotorOccasion\Testing\BrandFactory;
use NiekNijland\MotorOccasion\Testing\ResultFactory;

$fake = new FakeMotorOccasion(
    brands: BrandFactory::makeMany(5),
    results: ResultFactory::makeMany(10),
);

// Use it like the real client
$brands = $fake->getBrands();           // returns seeded brands
$results = $fake->search($criteria);    // paginates seeded results

// Assert calls were made
$fake->assertCalled('getBrands');
$fake->assertCalledTimes('search', 1);
$fake->assertNotCalled('getOffers');

// Simulate errors
$fake->shouldThrow(new MotorOccasionException('Service down'));
```

Fluent setters for configuration:

```php
$fake = (new FakeMotorOccasion())
    ->setBrands(BrandFactory::makeMany(3))
    ->setCategories(CategoryFactory::makeMany(5))
    ->setResults(ResultFactory::makeMany(20));
```

For detail pages, map results to details:

```php
$result = ResultFactory::make();
$detail = ListingDetailFactory::make(url: $result->url);

$fake->setDetail($result, $detail);
$fake->getDetail($result); // returns the seeded detail
```

#### DTO Factories

Every DTO has a factory with sensible defaults. Override only what you need:

```php
use NiekNijland\MotorOccasion\Testing\ResultFactory;
use NiekNijland\MotorOccasion\Testing\ListingDetailFactory;
use NiekNijland\MotorOccasion\Testing\SellerFactory;

// One-liner with all defaults
$result = ResultFactory::make();

// Override specific fields
$result = ResultFactory::make(brand: 'Honda', askingPrice: 5000, year: 2023);

// Generate multiple
$results = ResultFactory::makeMany(10);

// ListingDetail — no more 17-parameter constructors
$detail = ListingDetailFactory::make(color: 'ROOD', warranty: false);

// Seller variants
$dealer = SellerFactory::makeDealer();   // full dealer info (address, phone, etc.)
$private = SellerFactory::makePrivate(); // "Particulier" with no contact info
```

Available factories:

| Factory | Method(s) | Default |
|---|---|---|
| `BrandFactory` | `make()`, `makeMany()` | BMW |
| `CategoryFactory` | `make()`, `makeMany()` | Naked |
| `TypeFactory` | `make()`, `makeMany()` | R 1250 GS (BMW) |
| `SellerFactory` | `make()`, `makeDealer()`, `makePrivate()` | De Motor Shop |
| `ResultFactory` | `make()`, `makeMany()` | BMW R 1250 GS, EUR 18,950 |
| `ListingDetailFactory` | `make()` | BMW R 1250 GS with full specs |
| `EngineFactory` | `make()` | All fields null |
| `ChassisFactory` | `make()` | All fields null |
| `DimensionsFactory` | `make()` | All fields null |

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
