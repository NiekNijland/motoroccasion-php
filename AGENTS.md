# AGENTS.md

## Project Overview

PHP package (`nieknijland/motor-occasion-php`) that scrapes motoroccasion.nl to fetch motorcycle listings, brands, types, categories, offers, and listing details. Uses Guzzle for HTTP and DOMDocument/DOMXPath for HTML parsing. Standalone Composer library — no framework dependency. Requires PHP `^8.3`. Every PHP file starts with `declare(strict_types=1);`.

## Build & Run Commands

```bash
composer install                     # Install dependencies
composer test                        # Run unit tests (excludes integration)
composer test-integration            # Run integration tests (hits live site)
composer test-all                    # Run all test suites
composer test-coverage               # Unit tests with HTML/clover/JUnit coverage
composer format                      # Format with Laravel Pint (default config)
composer pint                        # Format with remote DIJ-digital Pint config
composer analyse                     # PHPStan static analysis (level 8)
composer rector                      # Run Rector refactoring
composer rector:dry-run              # Preview Rector changes
composer codestyle                   # Runs rector + pint + analyse sequentially

# Single test:
vendor/bin/phpunit --filter test_get_brands_parses_brand_options
```

### PHPUnit Configuration (`phpunit.xml.dist`)
- Two suites: **Unit** (`tests/`, excludes `IntegrationTest.php`) and **Integration** (only `IntegrationTest.php`)
- Execution order: random. Strict: `failOnWarning`, `failOnRisky`, `failOnEmptyTestSuite`, `beStrictAboutOutputDuringTests`
- Coverage source: `src/` directory

## Project Structure

```
src/
  MotorOccasion.php              # Main client — all public API methods
  MotorOccasionInterface.php     # Public API contract
  Parser/
    HtmlParser.php               # All HTML/DOM parsing logic
  Data/                          # DTOs (readonly classes) and enums
    Brand.php, Category.php, Type.php, Result.php, Seller.php
    ListingDetail.php, SearchCriteria.php, SearchResult.php
    Province.php                 # String-backed enum (12 Dutch provinces)
    OdometerUnit.php             # String-backed enum (KM, MI)
    LicenseCategory.php          # String-backed enum (A, A1, A2, AM, B)
  Exception/
    MotorOccasionException.php   # Single custom exception (extends RuntimeException)
  Testing/                       # Test helpers shipped with the package
    FakeMotorOccasion.php        # In-memory fake implementing MotorOccasionInterface
    BrandFactory.php, CategoryFactory.php, TypeFactory.php
    ResultFactory.php, SellerFactory.php, ListingDetailFactory.php
tests/
  MotorOccasionTest.php          # Main unit tests (~50 tests)
  IntegrationTest.php            # Live site tests (excluded from default suite)
  ArrayCache.php                 # In-memory PSR-16 cache for tests
  Testing/
    FactoryTest.php              # Tests for testing factories
    FakeMotorOccasionTest.php    # Tests for the fake client
  Fixtures/                      # HTML/JSON fixtures for mocked HTTP responses
    homepage.html, search-form.json, results.html
    results-pagination.html, detail.html, offers.html
```

## Code Style & Conventions

### Formatting & Tooling
- **Laravel Pint** for formatting (auto-committed in CI via remote DIJ-digital config)
- **PHPStan level 8** for static analysis (analyzes `src/` only)
- **Rector** for automated refactoring (`src/` and `tests/`; sets: deadCode, typeDeclarations, privatization, instanceOf, codeQuality, codingStyle)
- 4 spaces, LF line endings, final newline, trim trailing whitespace, UTF-8

### Namespaces (PSR-4)
- Source: `NiekNijland\MotorOccasion\` -> `src/`
- Tests: `NiekNijland\MotorOccasion\Tests\` -> `tests/`
- One class per file, filename matches class name

### Imports
- Fully qualified `use` statements at the top (no inline `\Namespace\Class`)
- Order: PHP built-ins (`Closure`, `JsonException`), then vendor (`GuzzleHttp\*`, `Psr\*`), then project
- No unused imports

### Naming Conventions
- Classes: PascalCase (`MotorOccasion`, `SearchResult`, `ListingDetail`)
- Methods/properties: camelCase (`getBrands`, `$odometerReading`)
- Constants: UPPER_SNAKE_CASE, typed (`private const string BASE_URL`)
- Private methods use verb prefixes: `parse*`, `collect*`, `ensure*`, `fetch*`, `apply*`, `build*`
- Public data methods use `get*` prefix

### Types & Visibility
- Native PHP types on all parameters and returns; PHPDoc only for array shapes and inline `@var`
- Array returns documented as `@return Brand[]` or `@return array<int, array{0: string, 1: string}>`
- Internal state is `private`; constructor accepts optional DI params (`?ClientInterface`, `?CacheInterface`)

### DTOs
- All `readonly class` with promoted constructor properties
- Nullable properties: `?Type $prop = null`
- Complex DTOs have `toArray()` and `static fromArray()` with PHPDoc array shape types
- Named arguments in all constructor calls
- Enum values serialized as strings in `toArray()`, deserialized via `::from()`/`::tryFrom()` in `fromArray()`
- `ValueError` from invalid enums caught and wrapped in `MotorOccasionException` with `previous:` chaining

### Enums
- String-backed enums (`Province`, `OdometerUnit`, `LicenseCategory`)
- Province includes custom `tryFromAbbreviation()` factory using `match`

### Error Handling
- Single exception: `MotorOccasionException extends RuntimeException`
- Thrown on HTTP failures (non-200 status), JSON decode errors, parse failures, invalid enum values
- Always chain underlying exception via `previous:` parameter
- Document `@throws MotorOccasionException` in PHPDoc

### DOM Parsing (in `Parser/HtmlParser.php`)
- `@$doc->loadHTML(...)` with error suppression for malformed HTML
- Wrap fragments in container before loading: `'<html><body><div id="...">' . $html . '</div></body></html>'`
- `DOMXPath` with `contains(@class, ...)` for class queries; `getElementById()` for ID lookups
- Check `instanceof DOMElement` before accessing element methods
- Extract text with `->textContent` and `trim()`

## Testing Patterns

### Unit Tests (`MotorOccasionTest`)
- PHPUnit 10.x; snake_case `test_` prefix naming: `test_get_brands_parses_brand_options`
- HTTP mocked via `GuzzleHttp\Handler\MockHandler` + `HandlerStack` — zero real HTTP calls
- Helper methods: `sessionResponse()`, `okResponse()`, fixture loaders
- Pattern: create MockHandler -> instantiate MotorOccasion with mock client -> call method -> assert
- Prefer `assertSame()` over `assertEquals()` for strict checks
- Use `assertInstanceOf()`, `assertCount()`, `assertNull()`, `expectException()`
- Test DTO round-trips with `toArray()`/`fromArray()`

### Testing Utilities (`src/Testing/`)
- `FakeMotorOccasion`: in-memory fake implementing `MotorOccasionInterface` with call tracking, assertions (`assertCalled()`, `assertNotCalled()`, `assertCalledTimes()`), and error simulation (`shouldThrow()`)
- Factories: static `make()` with named params and defaults; `makeMany(int $count)` for collections

## CI/CD

- **Tests**: runs on push; matrix of PHP 8.3 + 8.4, ubuntu + windows, prefer-lowest + prefer-stable
- **Code style**: Rector + Pint + PHPStan on push; auto-commits formatting fixes
- **Dependabot**: weekly checks; auto-merges minor/patch updates

## Key Dependencies

- `guzzlehttp/guzzle` ^7.0 — HTTP client
- `psr/simple-cache` ^3.0 — optional caching interface
- `ext-dom` — DOM parsing
- `phpunit/phpunit` ^10.3.2 — testing (dev)
- `laravel/pint` ^1.0 — code formatting (dev)
- `phpstan/phpstan` ^2.0 — static analysis (dev)
- `rector/rector` ^2.0 — automated refactoring (dev)
