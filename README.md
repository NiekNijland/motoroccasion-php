# motor-occasion-php

PHP client for [motoroccasion.nl](https://www.motoroccasion.nl) -- the largest used motorcycle marketplace in the Netherlands. Search listings, browse brands and types, view special dealer offers, and fetch full listing details with photos and specifications.

## Requirements

- PHP 8.3+
- `ext-dom`

## Installation

```bash
composer require nieknijland/motor-occasion-php
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
    echo "{$listing->brand} {$listing->model} - EUR {$listing->price} ({$listing->year})\n";
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
    license: 'A2',
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
| `license` | `?string` | License type (A, A1, A2, AM, B) |
| `electric` | `?bool` | Electric motorcycles only |
| `vatDeductible` | `?bool` | VAT-deductible (BTW) only |
| `postalCode` | `?string` | Dutch postal code for proximity search |
| `radius` | `?int` | Radius in km (used with `postalCode`) |
| `keywords` | `?string` | Free-text search |
| `selection` | `?string` | Selection filter |
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
    $listing->brand;              // "YAMAHA"
    $listing->model;              // "MT 07"
    $listing->price;              // 6450 (EUR, int)
    $listing->year;               // 2019
    $listing->odometerReading;    // 23500
    $listing->odometerReadingUnit; // "KM"
    $listing->image;              // "https://www.motoroccasion.nl/fotos/..."
    $listing->url;                // "/motor/12345/yamaha-mt-07"
    $listing->originalPrice;      // 7200 (or null)
    $listing->monthlyLease;       // 134 (EUR/month, or null)
    $listing->seller->name;       // "Goedhart Motoren"
    $listing->seller->province;   // Province::ZuidHolland (or null)
    $listing->seller->website;    // "https://www.goedhart.nl"
}
```

### Listing details

Fetch the full detail page for a listing. This provides additional info like color, power, photos, technical specs, description, and dealer contact details.

```php
$detail = $client->getDetail($listing);

$detail->brand;            // "YAMAHA"
$detail->model;            // "MT 07"
$detail->price;            // 6450
$detail->originalPrice;    // 7200 (or null)
$detail->monthlyLease;     // 134 (EUR/month, or null)
$detail->year;             // 2019
$detail->odometerReading;  // 23500
$detail->odometerReadingUnit; // "KM"
$detail->color;            // "ZWART"
$detail->powerKw;          // 55 (kW, or null)
$detail->license;          // "A2"
$detail->warranty;         // true (or null)
$detail->images;           // ["https://...photo1.jpg", "https://...photo2.jpg", ...]
$detail->description;      // "Mooie MT-07 in perfecte staat..."
$detail->specifications;   // ["Merk" => "YAMAHA", "Model" => "MT 07", "Cilinderinhoud" => "689 cc", ...]
$detail->url;              // "/motor/12345/yamaha-mt-07"
$detail->seller->name;     // "Goedhart Motoren"
$detail->seller->website;  // "https://www.goedhart.nl"
$detail->seller->address;  // "Industrieweg 12"
$detail->seller->city;     // "Gouda"
$detail->seller->phone;    // "0182-123456"
```

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

### Serialization

`Result`, `Seller`, and `ListingDetail` support `toArray()` and `fromArray()` for serialization (e.g. caching or storing in a database).

```php
$array = $listing->toArray();
// ['brand' => 'YAMAHA', 'model' => 'MT 07', 'price' => 6450, 'seller' => ['name' => '...', ...], ...]

$restored = Result::fromArray($array);
```

### Session management

The client automatically manages PHP session cookies required by motoroccasion.nl. Sessions are established on the first API call and reused for subsequent calls. To force a fresh session:

```php
$client->resetSession();
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

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
