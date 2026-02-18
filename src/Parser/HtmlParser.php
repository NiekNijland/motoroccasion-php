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
use NiekNijland\MotorOccasion\Data\LicenseCategory;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\OdometerUnit;
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

        @$doc->loadHTML('<html><body><div id="results-wrapper">' . $html . '</div></body></html>');

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

        @$doc->loadHTML('<html><body><div id="offers-wrapper">' . $html . '</div></body></html>');

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
        $price = $this->parsePriceFromDetail($priceText);

        // Original price (was-price)
        $originalPrice = null;
        $oldPriceNodes = $this->xpathQuery($xpath, "//*[contains(@class, 'full-tile-old-price')]");
        if ($oldPriceNodes->length > 0) {
            $originalPrice = $this->parsePriceFromDetail(trim((string) $oldPriceNodes->item(0)?->textContent));
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

        // Color
        $color = null;
        if (preg_match('/Kleur:\s*(\S+)/', $headerText, $colorMatch)) {
            $color = $colorMatch[1];
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

        // Specifications
        $specifications = [];
        $specNode = $doc->getElementById('deta-tech');
        if ($specNode !== null) {
            $rows = $specNode->getElementsByTagName('tr');
            /** @var DOMElement $row */
            foreach ($rows as $row) {
                $cells = $row->getElementsByTagName('td');
                if ($cells->length >= 2) {
                    $key = trim((string) $cells->item(0)?->textContent);
                    $value = trim((string) $cells->item(1)?->textContent);
                    if ($key !== '') {
                        $specifications[$key] = $value;
                    }
                }
            }
        }

        // Seller
        $seller = $this->parseDetailSeller($xpath);

        // Listing ID from URL
        $id = $this->extractListingIdFromUrl($url);

        return new ListingDetail(
            brand: $brand,
            model: $model,
            price: $price,
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
            id: $id,
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

        @$doc->loadHTML('<html lang="en"><body><select id="select">' . $html . '</select></body></html>');

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
     * @param Result[] $results
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
        $url = $href !== '' ? self::BASE_URL . $href : '';

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
        $price = 0;
        $priceNodes = $this->xpathQuery($xpath, './/span[contains(@class, "line-tile-price") and not(contains(@class, "line-tile-price-mnth"))]', $node);

        if ($priceNodes->length > 0) {
            $price = $this->parsePriceFromDetail(trim($priceNodes->item(0)->textContent ?? ''));
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
            price: $price,
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
     * @param Result[] $results
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
                    $url = self::BASE_URL . $parentLink->getAttribute('href');
                }
            }
        }

        // If no URL from photo, try content link
        if ($url === '') {
            $contentLinks = $this->xpathQuery($xpath, './/a[.//div[contains(@class, "big-tile-content")]]', $node);

            if ($contentLinks->length > 0) {
                $contentLink = $contentLinks->item(0);

                if ($contentLink instanceof DOMElement) {
                    $url = self::BASE_URL . $contentLink->getAttribute('href');
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
        $price = 0;
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

            if ($priceText !== '' && $price === 0) {
                $price = $this->parsePriceFromDetail($priceText);
            }
        }

        // Original price (old/strikethrough)
        $oldPriceNodes = $this->xpathQuery($xpath, './/*[contains(@class, "big-tile-price-old")]', $node);

        if ($oldPriceNodes->length > 0) {
            $originalPrice = $this->parsePriceFromDetail(trim((string) $oldPriceNodes->item(0)?->textContent));
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
            price: $price,
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
        // Title format: "Motoroccasion.nl, BMW R 4" or "BMW R 1250 GS - motoroccasion.nl"
        // Strip common prefix
        $title = preg_replace('/^Motoroccasion\.nl,\s*/i', '', $title);

        // Strip trailing " - motoroccasion.nl"
        $title = trim(explode(' - ', (string) $title)[0]);

        $parts = explode(' ', $title, 2);

        return [
            $parts[0],
            $parts[1] ?? '',
        ];
    }

    private function parsePriceFromDetail(string $text): int
    {
        // Handles "€ 12.500,-" or "€ 12.500" or similar
        if (preg_match('/\x{20AC}\s*([\d.]+)/u', $text, $match)) {
            return (int) str_replace('.', '', $match[1]);
        }

        return 0;
    }

    private function parseDetailSeller(DOMXPath $xpath): Seller
    {
        $dealerNodes = $this->xpathQuery($xpath, "//*[contains(@class, 'full-tile-dealer')]");

        $name = '';
        $website = '';
        $address = null;
        $city = null;
        $phone = null;

        if ($dealerNodes->length > 0) {
            $dealerNode = $dealerNodes->item(0);
            $dealerText = (string) $dealerNode?->textContent;

            // Dealer name: first link or first line of text
            $links = $this->xpathQuery($xpath, './/a', $dealerNode);
            if ($links->length > 0) {
                $firstLink = $links->item(0);

                if ($firstLink instanceof DOMElement) {
                    $name = trim($firstLink->textContent);
                    $website = $firstLink->getAttribute('href');
                }
            }

            // Phone: look for a pattern like digits with dashes
            if (preg_match('/([\d]{4}-[\d]+)/', $dealerText, $phoneMatch)) {
                $phone = $phoneMatch[1];
            }

            // Address and city: parse lines from dealer text
            $lines = array_map(trim(...), explode("\n", $dealerText));
            $lines = array_values(array_filter($lines, fn (string $line): bool => $line !== ''));

            // Typical structure: [Name, Address, City, "Plan de route", Phone]
            if (count($lines) >= 3) {
                $address = $lines[1];
                $city = $lines[2];

                // If city is "Plan de route" or the phone, try the line before
                if ($city === 'Plan de route' || preg_match('/^\d{4}-/', $city)) {
                    $city = null;
                }
            }
        }

        return new Seller(
            name: $name,
            province: null,
            website: $website,
            address: $address,
            city: $city,
            phone: $phone,
        );
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
            throw new MotorOccasionException('Invalid XPath expression: ' . $expression);
        }

        /** @var DOMNodeList<DOMNode> $result */
        return $result;
    }
}
