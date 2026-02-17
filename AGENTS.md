# AGENTS.md

## Project Overview

PHP package (`nieknijland/motor-occasion-php`) that scrapes motoroccasion.nl to fetch motorcycle listings, brands, types, categories, offers, and listing details. Uses Guzzle for HTTP and DOMDocument/DOMXPath for HTML parsing. No framework dependency — this is a standalone Composer library.

## Build & Run Commands

### Install Dependencies
```bash
composer install
```

### Run All Tests
```bash
composer test
# or directly:
vendor/bin/phpunit
```

### Run a Single Test
```bash
vendor/bin/phpunit --filter test_method_name
# Example:
vendor/bin/phpunit --filter test_get_brands_parses_brand_options
```

### Run Tests with Coverage
```bash
composer test-coverage
```

### Format Code (Laravel Pint)
```bash
composer format
# or with the remote DIJ-digital config:
composer pint
```

### PHPUnit Configuration
- Config file: `phpunit.xml.dist`
- Test directory: `tests/`
- Bootstrap: `vendor/autoload.php`
- Execution order: random
- Strict settings: `failOnWarning`, `failOnRisky`, `failOnEmptyTestSuite`, `beStrictAboutOutputDuringTests` are all enabled
- Coverage reports go to `build/coverage/` and `build/logs/`

## Project Structure

```
src/
  MotorOccasion.php          # Main client class — all public API methods
  Data/                      # DTOs (readonly classes)
    Brand.php
    Category.php
    ListingDetail.php
    Province.php             # Backed enum (string)
    Result.php
    SearchCriteria.php
    SearchResult.php
    Seller.php
    Type.php
  Exception/
    MotorOccasionException.php
tests/
  MotorOccasionTest.php      # All tests in a single file
  Fixtures/                  # HTML/JSON fixtures for mocked HTTP responses
    homepage.html
    search-form.json
    results.html
    detail.html
    offers.html
```

## Code Style & Conventions

### Formatting (Laravel Pint)
- Formatter: **Laravel Pint** (runs in CI on every push, auto-commits fixes)
- Indentation: 4 spaces (no tabs)
- Line endings: LF
- Final newline: required
- Trailing whitespace: trimmed
- Encoding: UTF-8

### PHP Version & Strict Types
- Requires PHP `^8.3`
- Every PHP file starts with `declare(strict_types=1);`

### Namespaces & Autoloading (PSR-4)
- Source: `NiekNijland\MotorOccasion\` → `src/`
- Tests: `NiekNijland\MotorOccasion\Tests\` → `tests/`
- One class per file, filename matches class name

### Imports
- Fully qualified `use` statements at the top (no inline `\Namespace\Class`)
- Group order: PHP built-in classes first, then vendor, then project classes
- No unused imports

### DTOs (Data Transfer Objects)
- All DTOs are `readonly class` with promoted constructor properties
- Simple DTOs (Brand, Category, Type) have only a constructor
- Complex DTOs (Result, Seller, ListingDetail) include `toArray()` and `static fromArray()` methods for serialization
- Use PHPDoc `@param` annotations with array shape types on `fromArray()` and `@return` on `toArray()`
- Nullable properties use `?Type` syntax and default to `null`
- Named arguments are used in all constructor calls

### Enums
- Province is a string-backed enum (`enum Province: string`)
- Includes a custom `tryFromAbbreviation()` factory using `match`

### Error Handling
- Single custom exception: `MotorOccasionException extends RuntimeException`
- Thrown on HTTP failures (non-200 status), JSON decode errors, and parse failures
- Use `previous:` parameter to chain underlying exceptions (e.g., `JsonException`)
- Methods document `@throws MotorOccasionException` in PHPDoc

### Naming Conventions
- Classes: PascalCase (`MotorOccasion`, `SearchResult`, `ListingDetail`)
- Methods: camelCase (`getBrands`, `getTypesForBrand`, `parseResultsHtml`)
- Properties: camelCase (`$odometerReading`, `$monthlyLease`)
- Private methods prefixed with verbs: `parse*`, `collect*`, `ensure*`, `fetch*`, `apply*`, `build*`
- Public methods use `get*` prefix for data retrieval
- Constants: UPPER_SNAKE_CASE (`BASE_URL`)

### Visibility & Access
- Class constants use typed constants (`private const string BASE_URL`)
- Internal state (`$httpClient`, `$cookieJar`, `$homepageHtml`, `$categories`) is `private`
- Constructor accepts `?ClientInterface` for dependency injection / testing

### Type Annotations
- Use native PHP types everywhere (no PHPDoc-only types for parameters/returns)
- Use PHPDoc `@var` for inline type hints where PHP cannot express the type (e.g., `/** @var DOMElement $child */`)
- Array return types use PHPDoc `@return` with generic syntax: `@return Brand[]`, `@return array<int, array{0: string, 1: string}>`

### DOM Parsing Patterns
- Use `@$doc->loadHTML(...)` with error suppression for malformed HTML
- Wrap HTML fragments in a container element before loading into DOMDocument
- Use `DOMXPath` with `contains(@class, ...)` for class-based queries
- Use `getElementById()` for ID-based lookups
- Check `instanceof DOMElement` before accessing element-specific methods
- Extract text with `->textContent` and clean with `trim()`

## Testing Patterns

### Test Framework
- PHPUnit 10.x
- Single test class: `MotorOccasionTest extends TestCase`

### Test Method Naming
- Snake_case with `test_` prefix: `test_get_brands_parses_brand_options`
- Descriptive names that explain what is being verified

### Mocking HTTP
- Uses `GuzzleHttp\Handler\MockHandler` + `HandlerStack` for deterministic HTTP mocking
- No real HTTP calls in tests
- Helper methods for common responses: `sessionResponse()`, `okResponse()`
- HTML/JSON fixtures stored in `tests/Fixtures/`

### Test Structure Pattern
1. Create `MockHandler` with ordered responses (session + API responses)
2. Instantiate `MotorOccasion` with mock client
3. Call the method under test
4. Assert with `assertSame()` (strict), `assertInstanceOf()`, `assertCount()`, `assertNull()`

### Assertions
- Prefer `assertSame()` over `assertEquals()` for strict type+value checks
- Use `assertInstanceOf()` to verify DTO types
- Use `assertCount()` for collection lengths
- Test round-trip serialization for DTOs with `toArray()`/`fromArray()`

## CI/CD

- **Tests**: run on push (PHP 8.4, ubuntu + windows, prefer-lowest + prefer-stable)
- **Code style**: Laravel Pint runs on push, auto-commits fixes
- **Dependabot**: enabled for dependency updates

## Key Dependencies

- `guzzlehttp/guzzle` ^7.0 — HTTP client
- `ext-dom` — DOM parsing
- `phpunit/phpunit` ^10.3.2 — testing (dev)
- `laravel/pint` ^1.0 — code formatting (dev)
- `spatie/ray` ^1.28 — debugging (dev)
