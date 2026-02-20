# Changelog

All notable changes to `motor-occasion-php` will be documented in this file.

## Unreleased

### Added

- `PriceType` string-backed enum with cases: `Asking`, `OnRequest`, `Negotiable`, `Bidding`
  - `Asking` — regular asking price (e.g. `€ 12.500,-`)
  - `OnRequest` — "prijs op aanvraag" / no price available
  - `Negotiable` — "n.o.t.k." / "nader overeen te komen"
  - `Bidding` — "bieden" / "bieding"
- `priceType` field on `Result` and `ListingDetail` DTOs
- `Engine`, `Chassis`, and `Dimensions` sub-DTOs to group technical specifications on `ListingDetail`
  - `Engine` (16 fields): `capacityCc`, `type`, `cylinders`, `valves`, `boreAndStroke`, `compressionRatio`, `fuelDelivery`, `fuelType`, `isElectric`, `ignition`, `maxTorque`, `clutch`, `gearbox`, `driveType`, `starter`, `topSpeed`
  - `Chassis` (8 fields): `abs`, `frameType`, `frontSuspension`, `rearSuspension`, `frontBrake`, `rearBrake`, `frontTire`, `rearTire`
  - `Dimensions` (7 fields): `seatHeightMm`, `wheelbaseMm`, `lengthMm`, `widthMm`, `heightMm`, `tankCapacityLiters`, `weightKg`
- `EngineFactory`, `ChassisFactory`, `DimensionsFactory` testing utilities
- New `postalCode` field on `Seller` DTO, extracted from JavaScript variables on the page (authoritative source)
- Description key-value pairs are now parsed into the `specifications` array when the `deta-tech` table is empty
- Label synonym mapping for robust matching of Dutch/English specification keys (50+ synonyms)
- HTML5 named entity decoding for tech table values (`&period;`, `&comma;`, `&colon;`, `&sol;`, `&NewLine;`, etc.)
- Tech table keys are now normalized through the label synonym map (e.g. `Cilinder inhoud` -> `Cilinderinhoud`)
- Tech table and description specs are merged for field extraction (description values take precedence)

### Changed

- **Breaking:** `price` field renamed to `askingPrice` on both `Result` and `ListingDetail` DTOs. The field is now `?int` (nullable) — returns `null` when no numeric price is available (e.g. "prijs op aanvraag").
- **Breaking:** `Result` and `ListingDetail` constructors now require a `priceType: PriceType` parameter.
- **Breaking:** `toArray()`/`fromArray()` serialization uses `askingPrice` and `priceType` keys instead of `price`.
- `ListingDetail` constructor reduced from 61 to 30 parameters by grouping engine, chassis, and dimension fields into sub-DTOs. Access via `$detail->engine->capacityCc` instead of `$detail->engineCapacityCc`.
- Tech table specification keys in the `specifications` array are now normalized through the label synonym map. For example, `Cilinder inhoud` becomes `Cilinderinhoud`, `Comp. verhouding` becomes `Compressieverhouding`.
- `toArray()`/`fromArray()` serialization format changed: engine, chassis, and dimension fields are now nested under `engine`, `chassis`, and `dimensions` keys.

### Fixed

- Title parser now handles the new site format `"Brand - Model"` (was `"Brand Model"`)
- Seller parser updated for new DOM structure (`table full-tile full-tile-dealer` with `<table>` rows)
- Seller website URL is now cleaned of UTM tracking parameters
- `engineCapacityCc` now correctly extracts from tech table `Cilinder inhoud` (with space) in addition to description
- `postalCode` extraction no longer grabs the wrong postal code (EU representative address); now uses `gm_postcode` from JavaScript variables with fallback to last postal code in description
- Original price detection now supports both `full-tile-old-price` and `full-tile-price-old` CSS class variants
- Price parser handles non-breaking spaces (`&nbsp;`) between currency symbol and amount
- All extractors now check both tech table specs and description specs (previously only checked description)
