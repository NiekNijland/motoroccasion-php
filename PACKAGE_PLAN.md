# Package Plan - `nieknijland/motor-occasion-php`

Dit plan maakt de detaildata stabieler voor lokale filtering in MotorMonitor, zonder breaking changes.

## Doel

- Minder afhankelijk zijn van vrije tekst in `specifications`.
- Expliciete, typed velden beschikbaar maken voor lokale filters.
- Gegevens toevoegen die nodig zijn voor geo/filterkwaliteit.

## Huidige gaten

- `category`, `engine capacity`, `electric`, `vat deductibility` worden nu indirect uit tekst gehaald.
- Seller detail mist `postalCode` en `province` op detailniveau.
- Parserlogica is gevoelig voor labelvariaties op de bronwebsite.

## Scope (v1)

Voeg deze velden toe aan `ListingDetail`:

- `?string $categorySlug`
- `?int $engineCapacityCc`
- `?bool $isElectric`
- `?bool $vatDeductible`
- `?string $sellerPostalCode`
- `?Province $sellerProvince`

Belangrijk:

- Alles **nullable**.
- Bestaande velden en gedrag blijven intact.
- `specifications` blijft bestaan als fallback/debugbron.

## Implementatiestappen

## 1) Data-contract uitbreiden

Bestanden:

- `src/Data/ListingDetail.php`
- `src/Data/Seller.php`

Acties:

- Voeg bovengenoemde properties toe aan `ListingDetail` constructor.
- Update `fromArray()` en `toArray()` shapes.
- Voeg optioneel `postalCode` toe aan `Seller` (naast `address`, `city`, `phone`).
- Gebruik `Province::tryFrom(...)` / `Province::tryFromAbbreviation(...)` voor province parsing.

## 2) Parser robuust maken

Bestand:

- `src/Parser/HtmlParser.php`

Acties:

- Introduceer kleine extractormethodes per veld:
    - `extractCategorySlug(...)`
    - `extractEngineCapacityCc(...)`
    - `extractElectric(...)`
    - `extractVatDeductible(...)`
    - `extractSellerPostalCode(...)`
    - `extractSellerProvince(...)`
- Laat extractors meerdere labels/synoniemen ondersteunen (NL + EN waar relevant).
- Normaliseer tekst 1x centraal (lowercase, ascii, spaties).
- Houd parsing defensief: bij twijfel `null` teruggeven.

## 3) Label-synoniemen expliciet maken

In `HtmlParser`, voeg private constante mappen toe, bv.:

- category keys: `categorie`, `category`
- engine keys: `cilinderinhoud`, `engine capacity`, `displacement`
- fuel keys: `brandstof`, `fuel`, `aandrijving`
- vat keys: `marge/btw`, `btw`, `vat`

Doel: parsergedrag declaratief en makkelijk uit te breiden.

## 4) Tests uitbreiden (unit)

Bestanden:

- `tests/MotorOccasionTest.php`
- evt. extra parsergerichte tests in `tests/` als aparte class

Cases:

- Happy path: alle nieuwe velden worden gevuld.
- Labelvarianten: alternatieve keynamen worden herkend.
- Negatieve cases: onbekende labels geven `null`.
- Seller parsing:
    - postcode gevonden
    - postcode ontbreekt
    - province als afkorting en als volledige naam
- Backward compatibility:
    - oude `fromArray()` payloads zonder nieuwe keys blijven werken.

## 5) Test fixtures aanvullen

Bestanden:

- `tests/Fixtures/detail.html` (of extra fixtures)

Acties:

- Voeg fixtures toe met varianten in specs/verkoperblokken.
- Houd minimaal 2 detailvarianten aan om regressies te vangen.

## 6) API en BC-check

- Public API blijft compatible (alle nieuwe velden optioneel).
- Documenteer nieuwe velden in README bij `getDetail()`.
- Voeg changelog-entry toe als je changelog gebruikt.

## 7) Release-stappen

Aanbevolen volgorde:

1. Implementatie + tests groen.
2. `composer codestyle`.
3. `composer test-all`.
4. Tag als **minor** release (bijv. `vX.Y+1.0`) omdat API uitbreidt zonder break.

## Acceptatiecriteria

- Nieuwe typed velden zijn beschikbaar in `ListingDetail` en werken in unit tests.
- Parsing is tolerant voor labelvarianten en valt veilig terug op `null`.
- Bestaande integraties breken niet (oude payloads + bestaande tests blijven groen).
- README bevat voorbeeld met minimaal 1 van de nieuwe velden.

## Integratie in MotorMonitor daarna

Na package-release kun je in MotorMonitor:

- `ListingFilterAttributeExtractor` vereenvoudigen of verwijderen.
- Direct mappen op packagevelden i.p.v. heuristiek op `specifications`.
- Geo/filterbetrouwbaarheid verhogen door `sellerPostalCode` gericht te gebruiken.
