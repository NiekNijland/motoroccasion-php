<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Parser;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
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
use NiekNijland\MotorOccasion\Exception\MotorOccasionException;

class HtmlParser
{
    private const string BASE_URL = 'https://www.motoroccasion.nl';

    /**
     * @return Brand[]
     */
    public function parseBrandsHtml(string $html): array
    {
        $options = $this->parseSelectOptions($html);

        $brands = [];

        foreach ($options as [$value, $name]) {
            $brands[] = new Brand(name: $name, value: $value);
        }

        return $brands;
    }

    /**
     * @return Type[]
     */
    public function parseTypesHtml(string $html, Brand $brand): array
    {
        $options = $this->parseSelectOptions($html);

        $types = [];

        foreach ($options as [$value, $name]) {
            $types[] = new Type(name: $name, value: $value, brand: $brand);
        }

        return $types;
    }

    /**
     * @return Category[]
     */
    public function parseCategoriesFromHomepage(string $html): array
    {
        $doc = new DOMDocument(encoding: 'UTF-8');

        @$doc->loadHTML($html);

        /** @var ?DOMElement $select */
        $select = $doc->getElementById('category');

        if ($select === null) {
            return [];
        }

        $optionElements = $select->getElementsByTagName('option');

        $categories = [];

        /** @var DOMElement $option */
        foreach ($optionElements as $option) {
            $value = $option->getAttribute('value');
            $name = trim($option->nodeValue ?? '');

            if ($value === '' || $value === '-1' || $name === '') {
                continue;
            }

            $categories[] = new Category(name: $name, value: $value);
        }

        return $categories;
    }

    /**
     * @return Result[]
     *
     * @throws MotorOccasionException
     */
    public function parseResultsHtml(string $html): array
    {
        $doc = new DOMDocument(encoding: 'UTF-8');

        @$doc->loadHTML('<html><body><div id="results-wrapper">'.$html.'</div></body></html>');

        /** @var ?DOMElement $wrapper */
        $wrapper = $doc->getElementById('results-wrapper');

        if ($wrapper === null) {
            throw new MotorOccasionException('Could not parse results HTML');
        }

        $results = [];

        $this->collectResultNodes($wrapper, $results);

        return $results;
    }

    /**
     * @return Result[]
     *
     * @throws MotorOccasionException
     */
    public function parseOffersHtml(string $html): array
    {
        $doc = new DOMDocument(encoding: 'UTF-8');

        @$doc->loadHTML('<html><body><div id="offers-wrapper">'.$html.'</div></body></html>');

        /** @var ?DOMElement $wrapper */
        $wrapper = $doc->getElementById('offers-wrapper');

        if ($wrapper === null) {
            throw new MotorOccasionException('Could not parse offers HTML');
        }

        $results = [];

        $this->collectOfferNodes($wrapper, $results);

        return $results;
    }

    public function parseTotalCount(string $html): int
    {
        // 1. Exact count from "gevonden" text (e.g. "2.907 BMW gevonden", "1.329 aanbiedingen gevonden")
        if (preg_match('/[>"](\d[\d.]*)\s+.+?\s+gevonden/', $html, $matches)) {
            return (int) str_replace('.', '', $matches[1]);
        }

        // 2. Estimate from pagination buttons: extract the highest offset (s) and page size (max)
        //    from getPagination({..., max: '10', s: '2910'}, ...) calls embedded in the HTML.
        //    Estimated total = maxOffset + perPage (upper bound, last page may have fewer items).
        if (preg_match_all("/getPagination\(\{([^}]+)\}/", $html, $calls)) {
            $maxOffset = 0;
            $perPage = 0;

            foreach ($calls[1] as $params) {
                if (preg_match("/s:\s*'(\d+)'/", $params, $sm)) {
                    $maxOffset = max($maxOffset, (int) $sm[1]);
                }

                if ($perPage === 0 && preg_match("/max:\s*'(\d+)'/", $params, $mm)) {
                    $perPage = (int) $mm[1];
                }
            }

            if ($perPage > 0) {
                return $maxOffset + $perPage;
            }
        }

        return 0;
    }

    /**
     * Extract high-resolution image URLs from a detail page's HTML.
     *
     * Prefers data-src (1024x768) from gallery divs, falls back to img src (640x480).
     *
     * @return string[]
     */
    public function parseImagesFromDetailHtml(string $html): array
    {
        $doc = new DOMDocument(encoding: 'UTF-8');

        @$doc->loadHTML($html);

        return $this->extractGalleryImages($doc);
    }

    public function parseDetailHtml(string $html, string $url): ListingDetail
    {
        $doc = new DOMDocument(encoding: 'UTF-8');

        @$doc->loadHTML($html);

        $xpath = new DOMXPath($doc);

        // Brand and model from page title
        $titleNodes = $this->xpathQuery($xpath, '//title');
        $titleText = $titleNodes->length > 0 ? ($titleNodes->item(0)->nodeValue ?? '') : '';
        [$brand, $model] = $this->parseBrandAndModel($titleText);

        // Header area text for specs
        $headerNodes = $this->xpathQuery($xpath, "//*[contains(@class, 'full-tile-header')]");
        $headerText = $headerNodes->length > 0 ? (string) $headerNodes->item(0)?->textContent : '';

        // Price
        $priceNodes = $this->xpathQuery($xpath, "//*[contains(@class, 'full-tile-price')]");
        $priceText = $priceNodes->length > 0 ? trim((string) $priceNodes->item(0)?->textContent) : '';
        [$askingPrice, $priceType] = $this->parsePriceAndType($priceText);

        // Original price (was-price) — supports both class name variants
        $originalPrice = null;
        $oldPriceNodes = $this->xpathQuery($xpath, "//*[contains(@class, 'full-tile-old-price') or contains(@class, 'full-tile-price-old')]");
        if ($oldPriceNodes->length > 0) {
            [$originalPrice] = $this->parsePriceAndType(trim((string) $oldPriceNodes->item(0)?->textContent));
        }

        // Monthly lease
        $monthlyLease = null;
        if (preg_match('/Leasen\s+vanaf\s+\x{20AC}\s*([\d.]+)\s*p\/m/u', $headerText, $leaseMatch)) {
            $monthlyLease = (int) str_replace('.', '', $leaseMatch[1]);
        }

        // Year
        $year = 0;
        if (preg_match('/Bouwjaar:\s*(\d{4})/', $headerText, $yearMatch)) {
            $year = (int) $yearMatch[1];
        }

        // Odometer
        $odometerReading = 0;
        $odometerReadingUnit = OdometerUnit::Kilometers;
        if (preg_match('/Teller:\s*([\d.]+)\s*(Km|Mi)/i', $headerText, $odometerMatch)) {
            $odometerReading = (int) str_replace('.', '', $odometerMatch[1]);
            $odometerReadingUnit = OdometerUnit::tryFrom(strtoupper($odometerMatch[2])) ?? OdometerUnit::Kilometers;
        }

        // Color — capture full value (may be multi-word like "Mat Zwart")
        $color = null;
        if (preg_match('/Kleur:\s*(.+?)(?:\s*(?:Vermogen|Rijbewijs|Garantie|Tellerstand|Bouwjaar):|$)/s', $headerText, $colorMatch)) {
            $color = trim($colorMatch[1]);
        }

        // Treat placeholder-only color values (e.g. ".", "-", "--") as null
        if ($color !== null && preg_match('/^[\s.\-_]+$/', $color)) {
            $color = null;
        }

        // Power
        $powerKw = null;
        if (preg_match('/Vermogen:\s*(\d+)\s*kw/i', $headerText, $powerMatch)) {
            $powerKw = (int) $powerMatch[1];
        }

        // License
        $license = null;
        if (preg_match('/Rijbewijs:\s*(\S+)/', $headerText, $licenseMatch)) {
            $license = LicenseCategory::tryFrom($licenseMatch[1]);
        }

        // Warranty
        $warranty = null;
        if (preg_match('/Garantie:\s*(Ja|Nee)/i', $headerText, $warrantyMatch)) {
            $warranty = strtolower($warrantyMatch[1]) === 'ja';
        }

        // Images
        $images = $this->extractGalleryImages($doc);

        // Description
        $description = null;
        $descNode = $doc->getElementById('deta-omsc');
        if ($descNode !== null) {
            $descText = trim($descNode->textContent);
            if ($descText !== '') {
                $description = $descText;
            }
        }

        // Specifications from both sources
        $techTableSpecs = $this->parseSpecificationsFromTechTable($doc);
        $descriptionSpecs = $this->parseSpecificationsFromDescription($description);

        // The public specifications array prefers tech table, falls back to description
        $specifications = $techTableSpecs !== [] ? $techTableSpecs : $descriptionSpecs;

        // Merged specs for extractors: tech table as base, description overrides
        // (description has dealer-specific data like kenteken, BTW, schade)
        $allSpecs = array_merge($techTableSpecs, $descriptionSpecs);

        // Seller
        $postalCode = $this->extractPostalCode($doc, $description);
        $seller = $this->parseDetailSeller($xpath, $postalCode);

        // Listing ID from URL
        $id = $this->extractListingIdFromUrl($url);

        return new ListingDetail(
            brand: $brand,
            model: $model,
            askingPrice: $askingPrice,
            priceType: $priceType,
            originalPrice: $originalPrice,
            monthlyLease: $monthlyLease,
            year: $year,
            odometerReading: $odometerReading,
            odometerReadingUnit: $odometerReadingUnit,
            color: $color,
            powerKw: $powerKw,
            license: $license,
            warranty: $warranty,
            images: $images,
            description: $description,
            specifications: $specifications,
            url: $url,
            seller: $seller,
            engine: new Engine(
                capacityCc: $this->extractEngineCapacityCc($allSpecs),
                type: $this->extractStringSpec($allSpecs, 'Motor'),
                cylinders: $this->extractCylinders($allSpecs),
                valves: $this->extractValves($allSpecs),
                boreAndStroke: $this->extractStringSpec($allSpecs, 'Boring x slag'),
                compressionRatio: $this->extractStringSpec($allSpecs, 'Compressieverhouding'),
                fuelDelivery: $this->extractStringSpec($allSpecs, 'Brandstoftoevoer'),
                fuelType: $this->extractFuelType($allSpecs),
                isElectric: $this->extractIsElectric($allSpecs),
                ignition: $this->extractStringSpec($allSpecs, 'Ontsteking'),
                maxTorque: $this->extractStringSpec($allSpecs, 'Max. koppel'),
                clutch: $this->extractStringSpec($allSpecs, 'Koppeling'),
                gearbox: $this->extractStringSpec($allSpecs, 'Versnellingsbak'),
                driveType: $this->extractStringSpec($allSpecs, 'Overbrenging'),
                starter: $this->extractStringSpec($allSpecs, 'Starter'),
                topSpeed: $this->extractStringSpec($allSpecs, 'Topsnelheid'),
            ),
            chassis: new Chassis(
                abs: $this->extractAbs($allSpecs),
                frameType: $this->extractStringSpec($allSpecs, 'Frame'),
                frontSuspension: $this->extractStringSpec($allSpecs, 'Vering voor'),
                rearSuspension: $this->extractStringSpec($allSpecs, 'Vering achter'),
                frontBrake: $this->extractStringSpec($allSpecs, 'Remmen voor'),
                rearBrake: $this->extractStringSpec($allSpecs, 'Remmen achter'),
                frontTire: $this->extractStringSpec($allSpecs, 'Voorband'),
                rearTire: $this->extractStringSpec($allSpecs, 'Achterband'),
            ),
            dimensions: new Dimensions(
                seatHeightMm: $this->extractMillimeters($allSpecs, 'Zithoogte'),
                wheelbaseMm: $this->extractMillimeters($allSpecs, 'Wielbasis'),
                lengthMm: $this->extractMillimeters($allSpecs, 'Lengte'),
                widthMm: $this->extractMillimeters($allSpecs, 'Breedte'),
                heightMm: $this->extractMillimeters($allSpecs, 'Hoogte'),
                tankCapacityLiters: $this->extractTankCapacityLiters($allSpecs),
                weightKg: $this->extractWeightKg($allSpecs),
            ),
            id: $id,
            vatDeductible: $this->extractVatDeductible($allSpecs),
            licensePlate: $this->extractLicensePlate($allSpecs),
            damageStatus: $this->extractDamageStatus($allSpecs),
            bodyType: $this->extractBodyType($allSpecs),
            roadTaxStatus: $this->extractRoadTaxStatus($allSpecs),
            availableColors: $this->extractStringSpec($allSpecs, 'Leverbare kleuren'),
            isNew: $this->extractIsNew($allSpecs),
            modelYear: $this->extractModelYear($allSpecs),
            factoryWarrantyMonths: $this->extractFactoryWarrantyMonths($allSpecs),
            dealerDescription: $this->parseDealerDescription($doc),
        );
    }

    /**
     * Extract high-resolution image URLs from the imageGallery element.
     *
     * Prefers data-src (1024x768) from gallery divs, falls back to img src (640x480).
     *
     * @return string[]
     */
    private function extractGalleryImages(DOMDocument $doc): array
    {
        $images = [];
        $gallery = $doc->getElementById('imageGallery');

        if ($gallery === null) {
            return [];
        }

        foreach ($gallery->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            // Gallery items are <div data-src="...high-res..." data-thumb="...thumb..."><img src="...medium..." /></div>
            $dataSrc = $child->getAttribute('data-src');
            if ($dataSrc !== '') {
                $images[] = $dataSrc;

                continue;
            }

            // Fall back to <img src> inside the child element
            $imgNodes = $child->getElementsByTagName('img');
            if ($imgNodes->length > 0) {
                /** @var DOMElement $imgNode */
                $imgNode = $imgNodes->item(0);
                $src = $imgNode->getAttribute('src');
                if ($src !== '') {
                    $images[] = $src;
                }

                continue;
            }

            // Direct <img> elements (legacy/simplified HTML)
            if ($child->tagName === 'img') {
                $src = $child->getAttribute('src');
                if ($src !== '') {
                    $images[] = $src;
                }
            }
        }

        return $images;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function parseSelectOptions(string $html): array
    {
        $doc = new DOMDocument(encoding: 'UTF-8');

        @$doc->loadHTML('<html lang="en"><body><select id="select">'.$html.'</select></body></html>');

        /** @var ?DOMElement $select */
        $select = $doc->getElementById('select');

        if ($select === null) {
            return [];
        }

        $optionElements = $select->getElementsByTagName('option');

        $options = [];

        /** @var DOMElement $option */
        foreach ($optionElements as $option) {
            $value = $option->getAttribute('value');
            $name = $option->nodeValue;

            if ($value === '-1' || $name === null || $name === '') {
                continue;
            }

            $options[] = [$value, $name];
        }

        return $options;
    }

    /**
     * @param  Result[]  $results
     */
    private function collectResultNodes(DOMElement $element, array &$results): void
    {
        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $class = $child->getAttribute('class');

            if ($class === 'table line-tile') {
                $result = $this->parseResultNode($child);

                if ($result instanceof Result) {
                    $results[] = $result;
                }

                continue;
            }

            $this->collectResultNodes($child, $results);
        }
    }

    private function parseResultNode(DOMElement $node): ?Result
    {
        $ownerDocument = $node->ownerDocument;

        if (! $ownerDocument instanceof DOMDocument) {
            return null;
        }

        $xpath = new DOMXPath($ownerDocument);

        // Image and URL from first linked image
        $imgNodes = $this->xpathQuery($xpath, './/a/img', $node);

        if ($imgNodes->length === 0) {
            return null;
        }

        /** @var DOMElement $img */
        $img = $imgNodes->item(0);
        $image = $img->getAttribute('src');

        $link = $img->parentNode;
        $href = $link instanceof DOMElement ? $link->getAttribute('href') : '';
        $url = $href !== '' ? self::BASE_URL.$href : '';

        // Brand from tile-brandname span (strip trailing &nbsp;)
        $brand = '';
        $brandNodes = $this->xpathQuery($xpath, './/*[contains(@class, "tile-brandname")]', $node);

        if ($brandNodes->length > 0) {
            $brand = trim(str_replace("\xC2\xA0", '', $brandNodes->item(0)->textContent ?? ''));
        }

        // Model from tile-typename span
        $model = '';
        $typeNodes = $this->xpathQuery($xpath, './/*[contains(@class, "tile-typename")]', $node);

        if ($typeNodes->length > 0) {
            $model = trim($typeNodes->item(0)->textContent ?? '');
        }

        if ($brand === '' && $model === '') {
            return null;
        }

        // Price from line-tile-price span (not the monthly variant)
        $askingPrice = null;
        $priceType = PriceType::OnRequest;
        $priceNodes = $this->xpathQuery($xpath, './/span[contains(@class, "line-tile-price") and not(contains(@class, "line-tile-price-mnth"))]', $node);

        if ($priceNodes->length > 0) {
            [$askingPrice, $priceType] = $this->parsePriceAndType(trim($priceNodes->item(0)->textContent ?? ''));
        }

        // Year and odometer from line-tile-yearmls
        $year = 0;
        $odometerReading = 0;
        $odometerReadingUnit = OdometerUnit::Kilometers;
        $yearMlsNodes = $this->xpathQuery($xpath, './/*[contains(@class, "line-tile-yearmls")]', $node);

        if ($yearMlsNodes->length > 0) {
            $yearAndOdometerDebris = explode(', ', trim($yearMlsNodes->item(0)->textContent ?? ''));
            $year = (int) $yearAndOdometerDebris[0];

            $odometerDebris = explode(' ', $yearAndOdometerDebris[1] ?? '');
            $odometerReading = (int) str_replace('.', '', $odometerDebris[0]);
            $rawUnit = isset($odometerDebris[1]) ? strtoupper($odometerDebris[1]) : 'KM';

            $odometerReadingUnit = ($rawUnit === 'NIEUW')
                ? OdometerUnit::Kilometers
                : (OdometerUnit::tryFrom($rawUnit) ?? OdometerUnit::Kilometers);
        }

        // Seller from line-tile-merkdealer div
        $sellerName = '';
        $province = null;
        $website = '';
        $merkdealerNodes = $this->xpathQuery($xpath, './/*[contains(@class, "line-tile-merkdealer")]', $node);

        foreach ($merkdealerNodes as $merkdealerNode) {
            if (! $merkdealerNode instanceof DOMElement) {
                continue;
            }

            $links = $this->xpathQuery($xpath, './/a[@href]', $merkdealerNode);

            if ($links->length > 0) {
                /** @var DOMElement $sellerLink */
                $sellerLink = $links->item(0);
                $sellerName = trim($sellerLink->textContent);
                $website = $sellerLink->getAttribute('href');

                // Province from text after dealer name (e.g., "De Motor Shop, NH")
                $fullText = trim($merkdealerNode->textContent);

                if (preg_match('/,\s+(\S+)$/', $fullText, $provinceMatch)) {
                    $province = Province::tryFromAbbreviation(trim($provinceMatch[1]));
                }

                break;
            }
        }

        // Private seller fallback: text without link in merkdealer div
        if ($sellerName === '') {
            foreach ($merkdealerNodes as $merkdealerNode) {
                if (! $merkdealerNode instanceof DOMElement) {
                    continue;
                }

                $text = trim($merkdealerNode->textContent);

                if ($text === '' || $text === 'Merkdealer') {
                    continue;
                }

                if (preg_match('/^(.+?),\s+(\S+)$/', $text, $parts)) {
                    $sellerName = $parts[1];
                    $province = Province::tryFromAbbreviation(trim($parts[2]));
                } else {
                    $sellerName = $text;
                }

                break;
            }
        }

        return new Result(
            brand: $brand,
            model: $model,
            askingPrice: $askingPrice,
            priceType: $priceType,
            year: $year,
            odometerReading: $odometerReading,
            odometerReadingUnit: $odometerReadingUnit,
            image: $image,
            url: $url,
            seller: new Seller(
                name: $sellerName,
                province: $province,
                website: $website,
            ),
            id: $this->extractListingIdFromUrl($url),
            images: $image !== '' ? [$image] : [],
        );
    }

    /**
     * @param  Result[]  $results
     */
    private function collectOfferNodes(DOMElement $element, array &$results): void
    {
        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $class = $child->getAttribute('class');

            if (str_contains($class, 'big-tile') && str_contains($class, 'table')) {
                $result = $this->parseOfferNode($child);

                if ($result instanceof Result) {
                    $results[] = $result;
                }

                continue;
            }

            $this->collectOfferNodes($child, $results);
        }
    }

    private function parseOfferNode(DOMElement $node): ?Result
    {
        $ownerDocument = $node->ownerDocument;

        if (! $ownerDocument instanceof DOMDocument) {
            return null;
        }

        $xpath = new DOMXPath($ownerDocument);

        // Brand
        $brandNodes = $this->xpathQuery($xpath, './/*[contains(@class, "tile-brandname")]', $node);
        $brand = $brandNodes->length > 0 ? trim((string) $brandNodes->item(0)?->textContent) : '';

        // Model/Type
        $typeNodes = $this->xpathQuery($xpath, './/*[contains(@class, "tile-typename")]', $node);
        $model = $typeNodes->length > 0 ? trim((string) $typeNodes->item(0)?->textContent) : '';

        if ($brand === '' && $model === '') {
            return null;
        }

        // URL and image from first link with big-tile-photo
        $url = '';
        $image = '';
        $photoNodes = $this->xpathQuery($xpath, './/img[contains(@class, "big-tile-photo")]', $node);

        if ($photoNodes->length > 0) {
            $photoImg = $photoNodes->item(0);

            if ($photoImg instanceof DOMElement) {
                $image = $photoImg->getAttribute('src');

                // URL is from parent anchor
                $parentLink = $photoImg->parentNode;

                if ($parentLink instanceof DOMElement && $parentLink->tagName === 'a') {
                    $url = self::BASE_URL.$parentLink->getAttribute('href');
                }
            }
        }

        // If no URL from photo, try content link
        if ($url === '') {
            $contentLinks = $this->xpathQuery($xpath, './/a[.//div[contains(@class, "big-tile-content")]]', $node);

            if ($contentLinks->length > 0) {
                $contentLink = $contentLinks->item(0);

                if ($contentLink instanceof DOMElement) {
                    $url = self::BASE_URL.$contentLink->getAttribute('href');
                }
            }
        }

        // Year and odometer from big-tile-yearmls
        $year = 0;
        $odometerReading = 0;
        $odometerReadingUnit = OdometerUnit::Kilometers;
        $yearMlsNodes = $this->xpathQuery($xpath, './/*[contains(@class, "big-tile-yearmls")]', $node);

        if ($yearMlsNodes->length > 0) {
            $yearMlsText = trim((string) $yearMlsNodes->item(0)?->textContent);
            // Format: "Bj: 2025, Nieuw" or "Bj: 2020, 9270 Km"
            if (preg_match('/Bj:\s*(\d{4})/', $yearMlsText, $yearMatch)) {
                $year = (int) $yearMatch[1];
            }

            if (preg_match('/(\d[\d.]*)\s*(Km|Mi)/i', $yearMlsText, $odometerMatch)) {
                $odometerReading = (int) str_replace('.', '', $odometerMatch[1]);
                $odometerReadingUnit = OdometerUnit::tryFrom(strtoupper($odometerMatch[2])) ?? OdometerUnit::Kilometers;
            }
        }

        // Price - current price (not old, not monthly)
        $askingPrice = null;
        $priceType = PriceType::OnRequest;
        $originalPrice = null;
        $monthlyLease = null;

        $priceNodes = $this->xpathQuery($xpath, './/*[contains(@class, "big-tile-price") and not(contains(@class, "big-tile-price-old")) and not(contains(@class, "big-tile-price-mnth"))]', $node);

        foreach ($priceNodes as $priceNode) {
            if (! $priceNode instanceof DOMElement) {
                continue;
            }

            $priceClass = $priceNode->getAttribute('class');

            // Skip container divs, only process spans
            if ($priceNode->tagName !== 'span') {
                continue;
            }

            if (str_contains($priceClass, 'big-tile-price-old') || str_contains($priceClass, 'big-tile-price-mnth')) {
                continue;
            }

            $priceText = trim($priceNode->textContent);

            if ($priceText !== '' && $askingPrice === null) {
                [$askingPrice, $priceType] = $this->parsePriceAndType($priceText);
            }
        }

        // Original price (old/strikethrough)
        $oldPriceNodes = $this->xpathQuery($xpath, './/*[contains(@class, "big-tile-price-old")]', $node);

        if ($oldPriceNodes->length > 0) {
            [$originalPrice] = $this->parsePriceAndType(trim((string) $oldPriceNodes->item(0)?->textContent));
        }

        // Monthly lease
        $monthlyNodes = $this->xpathQuery($xpath, './/*[contains(@class, "big-tile-price-mnth")]', $node);

        if ($monthlyNodes->length > 0) {
            $monthlyText = trim((string) $monthlyNodes->item(0)?->textContent);
            // Format: "(€ 134,- /mnd)"
            if (preg_match('/\x{20AC}\s*\x{A0}?([\d.]+)/u', $monthlyText, $monthlyMatch)) {
                $monthlyLease = (int) str_replace('.', '', $monthlyMatch[1]);
            }
        }

        // Seller (company name from big-tile-company)
        $companyNodes = $this->xpathQuery($xpath, './/*[contains(@class, "big-tile-company")]', $node);
        $sellerName = $companyNodes->length > 0 ? trim((string) $companyNodes->item(0)?->textContent) : '';

        return new Result(
            brand: $brand,
            model: $model,
            askingPrice: $askingPrice,
            priceType: $priceType,
            year: $year,
            odometerReading: $odometerReading,
            odometerReadingUnit: $odometerReadingUnit,
            image: $image,
            url: $url,
            seller: new Seller(
                name: $sellerName,
                province: null,
                website: '',
            ),
            id: $this->extractListingIdFromUrl($url),
            originalPrice: $originalPrice,
            monthlyLease: $monthlyLease,
            images: $image !== '' ? [$image] : [],
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseBrandAndModel(string $title): array
    {
        // Strip common prefix "Motoroccasion.nl, "
        $title = preg_replace('/^Motoroccasion\.nl,\s*/i', '', $title);

        // Strip trailing " - motoroccasion.nl"
        $title = trim((string) preg_replace('/\s*-\s*motoroccasion.*$/i', '', (string) $title));

        // New format: "Bmw - R 27" (brand - model with dash separator)
        if (str_contains($title, ' - ')) {
            $parts = explode(' - ', $title, 2);

            return [
                trim($parts[0]),
                trim($parts[1] ?? ''),
            ];
        }

        // Legacy format: "BMW R 4" (space separated)
        $parts = explode(' ', $title, 2);

        return [
            $parts[0],
            $parts[1] ?? '',
        ];
    }

    /**
     * Parse price text and determine the price type.
     *
     * @return array{0: ?int, 1: PriceType}
     */
    private function parsePriceAndType(string $text): array
    {
        $normalized = mb_strtolower(trim($text));

        // Extract numeric price if present: "€ 12.500,-", "€\xC2\xA010.990,-"
        $amount = null;

        if (preg_match('/\x{20AC}[\s\x{A0}]*([\d.]+)/u', $text, $match)) {
            $amount = (int) str_replace('.', '', $match[1]);
        }

        // Detect price type from text
        if (preg_match('/\bbied(en|ing)?\b/u', $normalized)) {
            return [$amount, PriceType::Bidding];
        }

        if (preg_match('/\bn\.?\s*o\.?\s*t\.?\s*k\.?|nader\s+overeen\s+te\s+komen/u', $normalized)) {
            return [$amount, PriceType::Negotiable];
        }

        if ($amount !== null && $amount > 0) {
            return [$amount, PriceType::Asking];
        }

        if (preg_match('/op\s+aanvraag|p\.?\s*o\.?\s*a\.?/u', $normalized)) {
            return [null, PriceType::OnRequest];
        }

        // No price and no recognized keyword — default to on request
        return [null, PriceType::OnRequest];
    }

    private function parseDetailSeller(DOMXPath $xpath, ?string $postalCode = null): Seller
    {
        // Match the specific dealer table, not the dealerbuttons
        $dealerNodes = $this->xpathQuery($xpath, "//div[contains(@class, 'table') and contains(@class, 'full-tile-dealer')]");

        $name = '';
        $website = '';
        $address = null;
        $city = null;
        $phone = null;

        foreach ($dealerNodes as $dealerNode) {
            if (! $dealerNode instanceof DOMElement) {
                continue;
            }

            // Skip dealer nodes that don't contain an actual address table
            // (banner and "ook van deze verkoper" sections)
            $tableRows = $dealerNode->getElementsByTagName('tr');
            if ($tableRows->length === 0) {
                continue;
            }

            // Check if this is the address dealer block by looking for location icon or address text
            $hasLocationIcon = false;
            $imgNodes = $this->xpathQuery($xpath, './/img[contains(@src, "location")]', $dealerNode);
            if ($imgNodes->length > 0) {
                $hasLocationIcon = true;
            }

            if (! $hasLocationIcon) {
                continue;
            }

            // Extract dealer name from the bold cell in the first row
            $boldCells = $this->xpathQuery($xpath, './/td[contains(@class, "bold")]', $dealerNode);
            if ($boldCells->length > 0) {
                $name = trim((string) $boldCells->item(0)?->textContent);
            }

            // Extract address and city from the address cell (contains <br> separated lines)
            $addressCells = $this->xpathQuery($xpath, './/td[contains(@class, "cursor-pointer") and not(contains(@class, "bold"))]', $dealerNode);
            if ($addressCells->length > 0) {
                $addressCell = $addressCells->item(0);
                if ($addressCell instanceof DOMElement) {
                    $lines = [];
                    foreach ($addressCell->childNodes as $child) {
                        if ($child instanceof DOMElement && $child->tagName === 'span') {
                            continue; // Skip "Plan de route" span
                        }

                        $text = trim($child->textContent);
                        if ($text !== '' && $text !== 'Plan de route') {
                            $lines[] = $text;
                        }
                    }

                    if ($lines !== []) {
                        $address = $lines[0];
                    }

                    if (count($lines) >= 2) {
                        $city = $lines[1];
                    }
                }
            }

            // Extract phone from tel: link
            $telLinks = $this->xpathQuery($xpath, './/a[starts-with(@href, "tel:")]', $dealerNode);
            if ($telLinks->length > 0) {
                $telLink = $telLinks->item(0);
                if ($telLink instanceof DOMElement) {
                    $phone = trim($telLink->textContent);
                }
            }

            // Extract website from UTM link
            $websiteLinks = $this->xpathQuery($xpath, './/a[contains(@href, "utm_source=motoroccasion")]', $dealerNode);
            if ($websiteLinks->length > 0) {
                $websiteLink = $websiteLinks->item(0);
                if ($websiteLink instanceof DOMElement) {
                    $rawUrl = $websiteLink->getAttribute('href');
                    // Strip UTM parameters to get clean website URL
                    $parsed = parse_url($rawUrl);
                    if ($parsed !== false && isset($parsed['scheme'], $parsed['host'])) {
                        $website = $parsed['scheme'].'://'.$parsed['host'];
                        if (isset($parsed['path']) && $parsed['path'] !== '/') {
                            $website .= $parsed['path'];
                        }
                    }
                }
            }

            break; // Found the actual dealer block
        }

        return new Seller(
            name: $name,
            province: null,
            website: $website,
            address: $address,
            city: $city,
            phone: $phone,
            postalCode: $postalCode,
        );
    }

    /**
     * Parse specifications from the deta-tech table element.
     *
     * Decodes HTML5 named entities and normalizes keys through the label synonym map.
     *
     * @return array<string, string>
     */
    private function parseSpecificationsFromTechTable(DOMDocument $doc): array
    {
        $specifications = [];
        $specNode = $doc->getElementById('deta-tech');

        if ($specNode === null) {
            return [];
        }

        $rows = $specNode->getElementsByTagName('tr');

        /** @var DOMElement $row */
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length >= 2) {
                $key = trim((string) $cells->item(0)?->textContent);
                $value = $this->decodeHtmlEntities(trim((string) $cells->item(1)?->textContent));
                if ($key !== '' && $value !== '') {
                    $canonicalKey = $this->resolveCanonicalLabel($key);
                    $specifications[$canonicalKey] = $value;
                }
            }
        }

        return $specifications;
    }

    /**
     * Decode HTML5 named entities that DOMDocument may not handle.
     *
     * Motoroccasion.nl uses entities like &period;, &comma;, &colon;, &sol;, &NewLine;
     * in tech table values instead of literal characters.
     */
    private function decodeHtmlEntities(string $text): string
    {
        // First try PHP's built-in decoder with HTML5 support
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Handle any remaining named entities that PHP may miss
        $replacements = [
            '&period;' => '.',
            '&comma;' => ',',
            '&colon;' => ':',
            '&sol;' => '/',
            '&quest;' => '?',
            '&NewLine;' => "\n",
            '&semi;' => ';',
            '&excl;' => '!',
            '&num;' => '#',
            '&plus;' => '+',
            '&equals;' => '=',
            '&lpar;' => '(',
            '&rpar;' => ')',
            '&lsqb;' => '[',
            '&rsqb;' => ']',
            '&ast;' => '*',
            '&apos;' => "'",
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $decoded);
    }

    /**
     * Label synonyms for matching description/tech table keys to canonical spec keys.
     *
     * Keys are canonical names, values are lowercase synonyms that map to them.
     *
     * @var array<string, list<string>>
     */
    private const array LABEL_SYNONYMS = [
        // Basic listing info
        'Merk' => ['merk', 'brand', 'make'],
        'Model' => ['model', 'type'],
        'Bouwjaar' => ['bouwjaar', 'year', 'jaar'],
        'Kleur' => ['kleur', 'color', 'colour'],
        'Vermogen' => ['vermogen', 'power', 'pk', 'kw'],
        'Tellerstand' => ['tellerstand', 'kilometerstand', 'mileage', 'odometer', 'km stand'],
        'Kenteken' => ['kenteken', 'license plate', 'registration'],
        'Rijbewijs' => ['rijbewijs', 'license', 'licence'],
        'Garantie' => ['garantie', 'warranty'],

        // Engine & drivetrain
        'Cilinderinhoud' => ['cilinderinhoud', 'motorinhoud', 'engine capacity', 'displacement', 'cilinder inhoud'],
        'Aantal cilinders' => ['aantal cilinders', 'cilinders', 'cylinders'],
        'Brandstofsoort' => ['brandstofsoort', 'brandstof', 'fuel', 'fuel type'],
        'Motor' => ['motor', 'engine type', 'motortype'],
        'Kleppen' => ['kleppen', 'valves'],
        'Boring x slag' => ['boring x slag', 'bore x stroke', 'bore and stroke'],
        'Compressieverhouding' => ['compressieverhouding', 'comp. verhouding', 'compression ratio'],
        'Brandstoftoevoer' => ['brandstoftoevoer', 'brandstof toevoer', 'fuel delivery', 'fuel injection'],
        'Ontsteking' => ['ontsteking', 'ignition'],
        'Max. koppel' => ['max. koppel', 'max koppel', 'maximum koppel', 'max torque'],
        'Koppeling' => ['koppeling', 'clutch'],
        'Versnellingsbak' => ['versnellingsbak', 'gearbox', 'transmissie', 'transmission'],
        'Overbrenging' => ['overbrenging', 'drive type', 'final drive', 'aandrijftype', 'aandrijving'],
        'Starter' => ['starter'],
        'Topsnelheid' => ['topsnelheid', 'top speed', 'max speed'],

        // Chassis & dimensions
        'ABS' => ['abs'],
        'Frame' => ['frame', 'frame type', 'frametype'],
        'Vering voor' => ['vering voor', 'front suspension', 'voorvering'],
        'Vering achter' => ['vering achter', 'rear suspension', 'achtervering'],
        'Remmen voor' => ['remmen voor', 'front brake', 'voorrem'],
        'Remmen achter' => ['remmen achter', 'rear brake', 'achterrem'],
        'Voorband' => ['voorband', 'front tire', 'front tyre'],
        'Achterband' => ['achterband', 'rear tire', 'rear tyre'],
        'Wielbasis' => ['wielbasis', 'wheelbase'],
        'Lengte' => ['lengte', 'length'],
        'Breedte' => ['breedte', 'width'],
        'Hoogte' => ['hoogte', 'height'],
        'Zithoogte' => ['zithoogte', 'seat height'],
        'Drooggewicht' => ['drooggewicht', 'dry weight', 'gewicht', 'weight', 'ledig gewicht', 'curb weight', 'kerb weight'],
        'Tankinhoud' => ['tankinhoud', 'tank capacity', 'tank'],
        'Leverbare kleuren' => ['leverbare kleuren', 'available colors', 'beschikbare kleuren'],

        // Financial & legal
        'BTW/Marge' => ['btw/marge', 'btw', 'marge/btw', 'vat', 'btw/marge regeling'],
        'Motorrijtuigenbelasting' => ['motorrijtuigenbelasting', 'mrb', 'road tax', 'wegenbelasting'],
        'Carrosserievorm' => ['carrosserievorm', 'carrosserie', 'body type', 'body'],
        'Schade' => ['schade', 'damage', 'schadeverleden'],

        // Description-only fields
        'Nieuw' => ['nieuw', 'new'],
        'Modeljaar' => ['modeljaar', 'model year', 'model jaar'],
        'Fabrieksgarantie' => ['fabrieksgarantie', 'factory warranty', 'fabriek garantie'],
    ];

    /**
     * Parse key-value pairs from the description text.
     *
     * Looks for patterns like "- Key: Value" or "Key: Value" on individual lines.
     *
     * @return array<string, string>
     */
    private function parseSpecificationsFromDescription(?string $description): array
    {
        if ($description === null || $description === '') {
            return [];
        }

        $specifications = [];
        $lines = explode("\n", $description);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || mb_strlen($line) > 200) {
                continue;
            }

            // Match "- Key: Value" or "Key: Value" patterns (keys may contain dots, hyphens, slashes)
            if (preg_match('/^-?\s*([\w\s\/.\-]+)\s*:\s*(.+)$/u', $line, $match)) {
                $rawKey = trim($match[1]);
                $value = trim($match[2]);

                if ($rawKey === '' || $value === '') {
                    continue;
                }

                // Map to canonical key using synonyms
                $canonicalKey = $this->resolveCanonicalLabel($rawKey);
                $specifications[$canonicalKey] = $value;
            }
        }

        return $specifications;
    }

    /**
     * Resolve a raw label to its canonical form using the synonym map.
     */
    private function resolveCanonicalLabel(string $rawLabel): string
    {
        $normalized = $this->normalizeLabel($rawLabel);

        foreach (self::LABEL_SYNONYMS as $canonical => $synonyms) {
            foreach ($synonyms as $synonym) {
                if ($normalized === $synonym) {
                    return $canonical;
                }
            }
        }

        return $rawLabel;
    }

    /**
     * Normalize a label for comparison: lowercase, trimmed.
     */
    private function normalizeLabel(string $label): string
    {
        return strtolower(trim($label));
    }

    /**
     * Extract engine capacity in cc from specification values.
     *
     * Matches patterns like "245 cc", "1200cc", "998 CC", and handles Dutch
     * thousands separators ("1.832 cc" -> 1832) and decimal values
     * ("499.6 cc" -> 500).
     *
     * @param  array<string, string>  $specs
     */
    private function extractEngineCapacityCc(array $specs): ?int
    {
        if (isset($specs['Cilinderinhoud']) && preg_match('/([\d][.\d,]*\d)\s*cc/i', $specs['Cilinderinhoud'], $match)) {
            return $this->parseDutchInteger($match[1]);
        }

        // Single digit cc edge case (e.g. scooters)
        if (isset($specs['Cilinderinhoud']) && preg_match('/(\d)\s*cc/i', $specs['Cilinderinhoud'], $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * Determine if the motorcycle is electric from the fuel type specification.
     *
     * @param  array<string, string>  $specs
     */
    private function extractIsElectric(array $specs): ?bool
    {
        $fuelType = $this->extractFuelType($specs);

        if ($fuelType === null) {
            return null;
        }

        $normalized = $this->normalizeLabel($fuelType);

        return in_array($normalized, ['elektrisch', 'electric', 'electrisch', 'ev'], true);
    }

    /**
     * Determine VAT deductibility from the BTW/Marge specification.
     *
     * @param  array<string, string>  $specs
     */
    private function extractVatDeductible(array $specs): ?bool
    {
        if (! isset($specs['BTW/Marge'])) {
            return null;
        }

        $normalized = $this->normalizeLabel($specs['BTW/Marge']);

        if (str_contains($normalized, 'aftrekbaar') && ! str_contains($normalized, 'niet')) {
            return true;
        }

        if (str_contains($normalized, 'btw verrekenbaar') || str_contains($normalized, 'excl')) {
            return true;
        }

        if (str_contains($normalized, 'niet aftrekbaar') || str_contains($normalized, 'marge')) {
            return false;
        }

        return null;
    }

    /**
     * Extract the fuel type string from specifications.
     *
     * @param  array<string, string>  $specs
     */
    private function extractFuelType(array $specs): ?string
    {
        return $specs['Brandstofsoort'] ?? null;
    }

    /**
     * Extract the number of cylinders from specifications.
     *
     * @param  array<string, string>  $specs
     */
    private function extractCylinders(array $specs): ?int
    {
        if (! isset($specs['Aantal cilinders'])) {
            return null;
        }

        if (preg_match('/(\d+)/', $specs['Aantal cilinders'], $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * Extract the license plate from specifications.
     *
     * @param  array<string, string>  $specs
     */
    private function extractLicensePlate(array $specs): ?string
    {
        return $specs['Kenteken'] ?? null;
    }

    /**
     * Extract the damage status from specifications.
     *
     * @param  array<string, string>  $specs
     */
    private function extractDamageStatus(array $specs): ?string
    {
        return $specs['Schade'] ?? null;
    }

    /**
     * Extract the body type from specifications.
     *
     * @param  array<string, string>  $specs
     */
    private function extractBodyType(array $specs): ?string
    {
        return $specs['Carrosserievorm'] ?? null;
    }

    /**
     * Extract the road tax status from specifications.
     *
     * @param  array<string, string>  $specs
     */
    private function extractRoadTaxStatus(array $specs): ?string
    {
        return $specs['Motorrijtuigenbelasting'] ?? null;
    }

    /**
     * Extract ABS availability from specifications.
     *
     * @param  array<string, string>  $specs
     */
    private function extractAbs(array $specs): ?bool
    {
        if (! isset($specs['ABS'])) {
            return null;
        }

        $normalized = $this->normalizeLabel($specs['ABS']);

        return in_array($normalized, ['ja', 'yes', 'true', '1'], true);
    }

    /**
     * Extract a millimeter dimension from specifications.
     *
     * Matches patterns like "830 mm", "1535mm", and handles Dutch thousands
     * separators ("1,440 mm" -> 1440) and decimal values ("830.5 mm" -> 831).
     *
     * @param  array<string, string>  $specs
     */
    private function extractMillimeters(array $specs, string $key): ?int
    {
        if (! isset($specs[$key])) {
            return null;
        }

        if (preg_match('/([\d][.\d,]*\d)\s*mm\b/', $specs[$key], $match)) {
            return $this->parseDutchInteger($match[1]);
        }

        // Single digit followed by mm (e.g. "5 mm" edge case)
        if (preg_match('/(\d)\s*mm\b/', $specs[$key], $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * Extract tank capacity in liters from specifications.
     *
     * Handles Dutch decimal notation (comma as separator): "16,9 liter" -> 16.9.
     * Also handles plain integers: "169 liter" -> 169.0.
     *
     * @param  array<string, string>  $specs
     */
    private function extractTankCapacityLiters(array $specs): ?float
    {
        if (! isset($specs['Tankinhoud'])) {
            return null;
        }

        if (preg_match('/([\d]+(?:[.,]\d+)?)\s*(?:liter|l\b)/i', $specs['Tankinhoud'], $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }

        return null;
    }

    /**
     * Extract dry weight in kilograms from specifications.
     *
     * Matches patterns like "208 kg", "208kg", "1,200 kg", or plain "208".
     * Handles Dutch thousands separators and decimal values ("148.5 kg" -> 149).
     *
     * @param  array<string, string>  $specs
     */
    private function extractWeightKg(array $specs): ?int
    {
        if (! isset($specs['Drooggewicht'])) {
            return null;
        }

        if (preg_match('/([\d][.\d,]*\d)\s*(?:\(.*?\))?\s*kg/i', $specs['Drooggewicht'], $match)) {
            return $this->parseDutchInteger($match[1]);
        }

        // Single digit weight edge case
        if (preg_match('/(\d)\s*kg/i', $specs['Drooggewicht'], $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * Parse a Dutch-formatted number string into an integer.
     *
     * Distinguishes between thousands separators (dot/comma followed by exactly
     * 3 digits at end) and decimal points (1-2 digits at end).
     *
     * Examples:
     *   "1.832"  -> 1832 (thousands separator: 3 digits after dot)
     *   "1,440"  -> 1440 (thousands separator: 3 digits after comma)
     *   "499.6"  -> 500  (decimal: 1 digit after dot, rounded)
     *   "148.5"  -> 149  (decimal: 1 digit after dot, rounded)
     *   "997.62" -> 998  (decimal: 2 digits after dot, rounded)
     *   "208"    -> 208  (no separator)
     */
    private function parseDutchInteger(string $value): int
    {
        // No separator — plain integer
        if (! preg_match('/[.,]/', $value)) {
            return (int) $value;
        }

        // Last separator followed by exactly 3 digits → thousands separator
        if (preg_match('/[.,]\d{3}$/', $value)) {
            return (int) preg_replace('/[.,]/', '', $value);
        }

        // Last separator followed by 1-2 digits → decimal point
        if (preg_match('/^(.*)[.,](\d{1,2})$/', $value, $parts)) {
            $integerPart = (string) preg_replace('/[.,]/', '', $parts[1]);

            return (int) round((float) ($integerPart.'.'.$parts[2]));
        }

        // Fallback: strip all separators
        return (int) preg_replace('/[.,]/', '', $value);
    }

    /**
     * Extract a string specification value by canonical key.
     *
     * Returns the trimmed value or null if not present or empty.
     *
     * @param  array<string, string>  $specs
     */
    private function extractStringSpec(array $specs, string $key): ?string
    {
        $value = $specs[$key] ?? null;

        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * Extract the number of valves from specifications.
     *
     * @param  array<string, string>  $specs
     */
    private function extractValves(array $specs): ?int
    {
        if (! isset($specs['Kleppen'])) {
            return null;
        }

        if (preg_match('/(\d+)/', $specs['Kleppen'], $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * Determine if the motorcycle is listed as new from description specs.
     *
     * @param  array<string, string>  $specs
     */
    private function extractIsNew(array $specs): ?bool
    {
        if (! isset($specs['Nieuw'])) {
            return null;
        }

        $normalized = $this->normalizeLabel($specs['Nieuw']);

        return in_array($normalized, ['ja', 'yes', 'true', '1'], true);
    }

    /**
     * Extract the model year from specifications.
     *
     * @param  array<string, string>  $specs
     */
    private function extractModelYear(array $specs): ?int
    {
        if (! isset($specs['Modeljaar'])) {
            return null;
        }

        if (preg_match('/(\d{4})/', $specs['Modeljaar'], $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * Extract factory warranty duration in months from specifications.
     *
     * Matches patterns like "72 maanden", "24 months", or plain "72".
     *
     * @param  array<string, string>  $specs
     */
    private function extractFactoryWarrantyMonths(array $specs): ?int
    {
        if (! isset($specs['Fabrieksgarantie'])) {
            return null;
        }

        if (preg_match('/(\d+)\s*(?:maanden|months|mnd)?/i', $specs['Fabrieksgarantie'], $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * Parse dealer/company description from the deta-beom element.
     */
    private function parseDealerDescription(DOMDocument $doc): ?string
    {
        $node = $doc->getElementById('deta-beom');

        if ($node === null) {
            return null;
        }

        $text = trim($node->textContent);

        if ($text === '') {
            return null;
        }

        return $text;
    }

    /**
     * Extract the dealer postal code from JavaScript variables in the page.
     *
     * Parses `var gm_postcode = "4462GK"` from embedded `<script>` tags,
     * which is the authoritative source for the dealer's postal code.
     * Falls back to the last Dutch postal code found in the description text
     * (dealer info is typically at the end, avoiding EU representative addresses at the start).
     */
    private function extractPostalCode(DOMDocument $doc, ?string $description): ?string
    {
        // Primary: parse gm_postcode from JavaScript variables
        $scripts = $doc->getElementsByTagName('script');

        foreach ($scripts as $script) {
            $scriptText = $script->textContent;
            if (preg_match('/gm_postcode\s*=\s*["\'](\d{4})\s*([A-Z]{2})["\']/', $scriptText, $match)) {
                return $match[1].' '.$match[2];
            }
        }

        // Fallback: last postal code in description (dealer info is at the end)
        if ($description !== null && $description !== '' && preg_match_all('/\b(\d{4})\s*([A-Z]{2})\b/', $description, $matches, PREG_SET_ORDER)) {
            /** @var array{0: string, 1: string, 2: string} $last */
            $last = end($matches);

            return $last[1].' '.$last[2];
        }

        return null;
    }

    /**
     * Extract the numeric listing ID from a motoroccasion.nl URL.
     *
     * Supports two URL formats:
     *   - Search results: /motor/{id}/slug  (e.g. /motor/12345/bmw-r-1250-gs)
     *   - Offers: /motoren/slug-m{id}.html  (e.g. /motoren/suzuki-v-strom-800-m1598887.html)
     */
    private function extractListingIdFromUrl(string $url): ?int
    {
        // Search result URL: /motor/{id}/slug
        if (preg_match('#/motor/(\d+)/#', $url, $match)) {
            return (int) $match[1];
        }

        // Offer URL: /motoren/slug-m{id}.html
        if (preg_match('#-m(\d+)\.html$#', $url, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * @return DOMNodeList<DOMNode>
     */
    private function xpathQuery(DOMXPath $xpath, string $expression, ?DOMNode $contextNode = null): DOMNodeList
    {
        $result = $xpath->query($expression, $contextNode);

        if ($result === false) {
            throw new MotorOccasionException('Invalid XPath expression: '.$expression);
        }

        /** @var DOMNodeList<DOMNode> $result */
        return $result;
    }
}
