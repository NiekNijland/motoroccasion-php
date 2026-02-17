<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use JsonException;
use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Category;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\Province;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\SearchCriteria;
use NiekNijland\MotorOccasion\Data\SearchResult;
use NiekNijland\MotorOccasion\Data\Seller;
use NiekNijland\MotorOccasion\Data\Type;
use NiekNijland\MotorOccasion\Exception\MotorOccasionException;

class MotorOccasion
{
    private const string BASE_URL = 'https://www.motoroccasion.nl';

    private CookieJar $cookieJar;

    private ?string $homepageHtml = null;

    /** @var Category[]|null */
    private ?array $categories = null;

    public function __construct(private readonly ClientInterface $httpClient = new Client)
    {
        $this->cookieJar = new CookieJar;
    }

    /**
     * @return Brand[]
     *
     * @throws MotorOccasionException
     */
    public function getBrands(): array
    {
        $this->ensureSession();

        $json = $this->fetchSearchForm();

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new MotorOccasionException('Failed to decode brands response: '.$jsonException->getMessage(), previous: $jsonException);
        }

        return $this->parseBrandsHtml($data['brands']);
    }

    /**
     * Note: This method sets the brand filter in the server-side session as a side effect.
     * If you call search() afterwards without specifying a brand, the previously-set brand
     * may still be active. Use search() which resets the session to avoid stale filters.
     *
     * @return Type[]
     *
     * @throws MotorOccasionException
     */
    public function getTypesForBrand(Brand $brand): array
    {
        $this->ensureSession();
        $this->setSessionParam('br', $brand->value);

        $json = $this->fetchSearchForm();

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new MotorOccasionException('Failed to decode types response: '.$jsonException->getMessage(), previous: $jsonException);
        }

        return $this->parseTypesHtml($data['types'], $brand);
    }

    /**
     * @throws MotorOccasionException
     */
    public function search(SearchCriteria $criteria): SearchResult
    {
        // Reset session to clear stale search params from previous searches,
        // since server-side session params accumulate across calls.
        $this->resetSession();
        $this->ensureSession();

        $this->applySearchCriteria($criteria);

        $offset = ($criteria->page - 1) * $criteria->perPage;

        $html = $this->fetchAjaxResults('/mz.php', [
            'params[singleSelect]' => '1',
            'params[selectie]' => $criteria->selection ?? 'all',
            'params[a]' => 'check',
            'params[c]' => (string) $criteria->perPage,
            'params[order]' => 'default',
            'params[layout]' => 'line',
            'params[every]' => '10,6',
            'params[nr]' => 'true',
            'params[s]' => (string) $offset,
            'params[max]' => (string) $criteria->perPage,
            't' => (string) time(),
        ]);

        $results = $this->parseResultsHtml($html);
        $totalCount = $this->parseTotalCount($html);

        return new SearchResult(
            results: $results,
            totalCount: $totalCount,
            currentPage: $criteria->page,
            perPage: $criteria->perPage,
        );
    }

    /**
     * @return Category[]
     *
     * @throws MotorOccasionException
     */
    public function getCategories(): array
    {
        $this->ensureSession();

        if ($this->categories !== null) {
            return $this->categories;
        }

        if ($this->homepageHtml === null) {
            throw new MotorOccasionException('Homepage HTML not available for category parsing');
        }

        $this->categories = $this->parseCategoriesFromHomepage($this->homepageHtml);

        return $this->categories;
    }

    /**
     * @throws MotorOccasionException
     */
    public function getOffers(int $page = 1, int $perPage = 40): SearchResult
    {
        $this->ensureSession();

        $offset = ($page - 1) * $perPage;

        $html = $this->fetchAjaxResults('/ms.php', [
            'params[order]' => 'update',
            'params[max]' => (string) $perPage,
            'params[layout]' => 'big',
            'params[every]' => '10,8',
            'params[s]' => (string) $offset,
            'params[c]' => (string) $perPage,
            'params[nr]' => 'true',
            't' => (string) time(),
        ]);

        $results = $this->parseOffersHtml($html);
        $totalCount = $this->parseTotalCount($html);

        return new SearchResult(
            results: $results,
            totalCount: $totalCount,
            currentPage: $page,
            perPage: $perPage,
        );
    }

    /**
     * @throws MotorOccasionException
     */
    public function getDetail(Result $result): ListingDetail
    {
        $this->ensureSession();

        $url = self::BASE_URL.$result->url;

        $response = $this->httpClient->request('GET', $url, [
            'cookies' => $this->cookieJar,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not fetch detail page');
        }

        $html = $response->getBody()->getContents();

        return $this->parseDetailHtml($html, $result->url);
    }

    /**
     * Force a fresh session on the next request.
     */
    public function resetSession(): void
    {
        $this->cookieJar = new CookieJar;
        $this->homepageHtml = null;
        $this->categories = null;
    }

    /**
     * @param  array<string, string>  $query
     *
     * @throws MotorOccasionException
     */
    private function fetchAjaxResults(string $endpoint, array $query): string
    {
        $response = $this->httpClient->request('GET', self::BASE_URL.$endpoint, [
            'cookies' => $this->cookieJar,
            'query' => $query,
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not fetch AJAX results from '.$endpoint);
        }

        return $response->getBody()->getContents();
    }

    /**
     * @throws MotorOccasionException
     */
    private function ensureSession(): void
    {
        if ($this->cookieJar->getCookieByName('PHPSESSID') instanceof SetCookie) {
            return;
        }

        $response = $this->httpClient->request('GET', self::BASE_URL, [
            'cookies' => $this->cookieJar,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not retrieve session from motoroccasion.nl');
        }

        $this->homepageHtml = $response->getBody()->getContents();

        /** @var SetCookie|null $sessionCookie */
        $sessionCookie = $this->cookieJar->getCookieByName('PHPSESSID');

        if (! $sessionCookie instanceof SetCookie) {
            throw new MotorOccasionException('Could not retrieve session ID');
        }
    }

    /**
     * @throws MotorOccasionException
     */
    private function applySearchCriteria(SearchCriteria $criteria): void
    {
        $params = [];

        if ($criteria->brand instanceof Brand) {
            $params['br'] = $criteria->brand->value;
        }

        if ($criteria->type instanceof Type) {
            $params['ty'] = $criteria->type->value;
        }

        if ($criteria->category instanceof Category) {
            $params['ca'] = $criteria->category->value;
        }

        if ($criteria->priceMin !== null) {
            $params['pricfrom'] = (string) $criteria->priceMin;
        }

        if ($criteria->priceMax !== null) {
            $params['prictill'] = (string) $criteria->priceMax;
        }

        if ($criteria->yearMin !== null) {
            $params['yearfrom'] = (string) $criteria->yearMin;
        }

        if ($criteria->yearMax !== null) {
            $params['yeartill'] = (string) $criteria->yearMax;
        }

        if ($criteria->odometerMin !== null) {
            $params['milefrom'] = (string) $criteria->odometerMin;
        }

        if ($criteria->odometerMax !== null) {
            $params['miletill'] = (string) $criteria->odometerMax;
        }

        if ($criteria->engineCapacityMin !== null) {
            $params['ccfrom'] = (string) $criteria->engineCapacityMin;
        }

        if ($criteria->engineCapacityMax !== null) {
            $params['cctill'] = (string) $criteria->engineCapacityMax;
        }

        if ($criteria->powerMin !== null) {
            $params['kwfrom'] = (string) $criteria->powerMin;
        }

        if ($criteria->powerMax !== null) {
            $params['kwtill'] = (string) $criteria->powerMax;
        }

        if ($criteria->license !== null) {
            $params['rbw'] = $criteria->license;
        }

        if ($criteria->electric === true) {
            $params['electric'] = 'electric';
        }

        if ($criteria->vatDeductible === true) {
            $params['tax'] = 'tax';
        }

        if ($criteria->postalCode !== null) {
            $params['zip'] = $criteria->postalCode;
        }

        if ($criteria->radius !== null) {
            $params['radius'] = (string) $criteria->radius;
        }

        if ($criteria->keywords !== null) {
            $params['textsearch'] = $criteria->keywords;
        }

        if ($criteria->selection !== null) {
            $params['selectie'] = $criteria->selection;
        }

        foreach ($params as $key => $value) {
            $this->setSessionParam($key, $value);
        }
    }

    /**
     * @throws MotorOccasionException
     */
    private function setSessionParam(string $key, string $value): void
    {
        $response = $this->httpClient->request('GET', self::BASE_URL.'/mz.php', [
            'cookies' => $this->cookieJar,
            'query' => [
                'params['.$key.']' => $value,
                'params[a]' => 'check',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not set search parameter: '.$key);
        }
    }

    /**
     * @throws MotorOccasionException
     */
    private function fetchSearchForm(): string
    {
        $response = $this->httpClient->request('GET', self::BASE_URL.'/fs.php', [
            'cookies' => $this->cookieJar,
            'query' => ['s' => 'mz'],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not fetch search form');
        }

        return $response->getBody()->getContents();
    }

    /**
     * @return Brand[]
     */
    private function parseBrandsHtml(string $html): array
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
    private function parseTypesHtml(string $html, Brand $brand): array
    {
        $options = $this->parseSelectOptions($html);

        $types = [];

        foreach ($options as [$value, $name]) {
            $types[] = new Type(name: $name, value: $value, brand: $brand);
        }

        return $types;
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
     * @return Category[]
     */
    private function parseCategoriesFromHomepage(string $html): array
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
    private function parseResultsHtml(string $html): array
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
    private function parseOffersHtml(string $html): array
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
                    $url = $parentLink->getAttribute('href');
                }
            }
        }

        // If no URL from photo, try content link
        if ($url === '') {
            $contentLinks = $this->xpathQuery($xpath, './/a[.//div[contains(@class, "big-tile-content")]]', $node);

            if ($contentLinks->length > 0) {
                $contentLink = $contentLinks->item(0);

                if ($contentLink instanceof DOMElement) {
                    $url = $contentLink->getAttribute('href');
                }
            }
        }

        // Year and odometer from big-tile-yearmls
        $year = 0;
        $odometerReading = 0;
        $odometerReadingUnit = 'KM';
        $yearMlsNodes = $this->xpathQuery($xpath, './/*[contains(@class, "big-tile-yearmls")]', $node);

        if ($yearMlsNodes->length > 0) {
            $yearMlsText = trim((string) $yearMlsNodes->item(0)?->textContent);
            // Format: "Bj: 2025, Nieuw" or "Bj: 2020, 9270 Km"
            if (preg_match('/Bj:\s*(\d{4})/', $yearMlsText, $yearMatch)) {
                $year = (int) $yearMatch[1];
            }

            if (preg_match('/(\d[\d.]*)\s*(Km|Mi)/i', $yearMlsText, $odometerMatch)) {
                $odometerReading = (int) str_replace('.', '', $odometerMatch[1]);
                $odometerReadingUnit = strtoupper($odometerMatch[2]);
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
            originalPrice: $originalPrice,
            monthlyLease: $monthlyLease,
        );
    }

    private function parseTotalCount(string $html): int
    {
        // Pattern: "2.907 BMW gevonden" or "3 BMW C 1 gevonden" or "1.329 aanbiedingen gevonden"
        // Match after > (HTML tag) or " (JS string) to avoid false matches on type names like "R 1250 GS"
        if (preg_match('/[>"](\d[\d.]*)\s+.+?\s+gevonden/', $html, $matches)) {
            return (int) str_replace('.', '', $matches[1]);
        }

        return 0;
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
        $url = $link instanceof DOMElement ? $link->getAttribute('href') : '';

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
        $odometerReadingUnit = 'KM';
        $yearMlsNodes = $this->xpathQuery($xpath, './/*[contains(@class, "line-tile-yearmls")]', $node);

        if ($yearMlsNodes->length > 0) {
            $yearAndOdometerDebris = explode(', ', trim($yearMlsNodes->item(0)->textContent ?? ''));
            $year = (int) $yearAndOdometerDebris[0];

            $odometerDebris = explode(' ', $yearAndOdometerDebris[1] ?? '');
            $odometerReading = (int) str_replace('.', '', $odometerDebris[0]);
            $odometerReadingUnit = isset($odometerDebris[1]) ? strtoupper($odometerDebris[1]) : 'KM';

            if ($odometerReadingUnit === 'NIEUW') {
                $odometerReadingUnit = 'KM';
            }
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
        );
    }

    private function parseDetailHtml(string $html, string $url): ListingDetail
    {
        $doc = new DOMDocument(encoding: 'UTF-8');

        @$doc->loadHTML($html);

        $xpath = new DOMXPath($doc);

        // Brand and model from page title
        $titleNode = $this->xpathQuery($xpath, '//title')->item(0);
        $titleText = $titleNode->nodeValue ?? '';
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
        $odometerReadingUnit = 'KM';
        if (preg_match('/Teller:\s*([\d.]+)\s*(Km|Mi)/i', $headerText, $odometerMatch)) {
            $odometerReading = (int) str_replace('.', '', $odometerMatch[1]);
            $odometerReadingUnit = strtoupper($odometerMatch[2]);
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
            $license = $licenseMatch[1];
        }

        // Warranty
        $warranty = null;
        if (preg_match('/Garantie:\s*(Ja|Nee)/i', $headerText, $warrantyMatch)) {
            $warranty = strtolower($warrantyMatch[1]) === 'ja';
        }

        // Images
        $images = [];
        $gallery = $doc->getElementById('imageGallery');
        if ($gallery !== null) {
            $imgNodes = $gallery->getElementsByTagName('img');
            /** @var DOMElement $imgNode */
            foreach ($imgNodes as $imgNode) {
                $src = $imgNode->getAttribute('src');
                if ($src !== '') {
                    $images[] = $src;
                }
            }
        }

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
