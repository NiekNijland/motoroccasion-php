<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Category;
use NiekNijland\MotorOccasion\Data\LicenseCategory;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\SearchCriteria;
use NiekNijland\MotorOccasion\Data\SearchResult;
use NiekNijland\MotorOccasion\Data\SortOrder;
use NiekNijland\MotorOccasion\Data\Type;
use NiekNijland\MotorOccasion\Exception\MotorOccasionException;
use NiekNijland\MotorOccasion\Parser\HtmlParser;
use Psr\SimpleCache\CacheInterface;

class MotorOccasion implements MotorOccasionInterface
{
    private const string BASE_URL = 'https://www.motoroccasion.nl';

    private CookieJar $cookieJar;

    private ?string $homepageHtml = null;

    /** @var Category[]|null */
    private ?array $categories = null;

    private readonly HtmlParser $parser;

    /**
     * @param ClientInterface $httpClient HTTP client for making requests
     * @param CacheInterface|null $cache Optional PSR-16 cache for brands and categories
     * @param int $cacheTtl Cache TTL in seconds (default: 1 hour)
     * @param (Closure(): int)|null $clock Optional clock function returning a Unix timestamp, used instead of time()
     */
    public function __construct(
        private readonly ClientInterface $httpClient = new Client(),
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtl = 3600,
        private readonly ?Closure $clock = null,
    ) {
        $this->cookieJar = new CookieJar();
        $this->parser = new HtmlParser();
    }

    /**
     * @return Brand[]
     *
     * @throws MotorOccasionException
     */
    public function getBrands(): array
    {
        /** @var Brand[]|null $cached */
        $cached = $this->cache?->get('motor-occasion:brands');

        if ($cached !== null) {
            return $cached;
        }

        $this->ensureSession();

        $json = $this->fetchSearchForm();

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new MotorOccasionException('Failed to decode brands response: ' . $jsonException->getMessage(), previous: $jsonException);
        }

        $brands = $this->parser->parseBrandsHtml($data['brands']);

        $this->cache?->set('motor-occasion:brands', $brands, $this->cacheTtl);

        return $brands;
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
            throw new MotorOccasionException('Failed to decode types response: ' . $jsonException->getMessage(), previous: $jsonException);
        }

        return $this->parser->parseTypesHtml($data['types'], $brand);
    }

    /**
     * @throws MotorOccasionException
     */
    public function search(SearchCriteria $criteria): SearchResult
    {
        // Reset session to clear stale search params from previous searches,
        // since server-side session params accumulate across calls.
        // Uses clearSession() instead of resetSession() to avoid invalidating
        // the external cache (brands/categories are not affected by search params).
        $this->clearSession();
        $this->ensureSession();

        $this->applySearchCriteria($criteria);

        $offset = ($criteria->page - 1) * $criteria->perPage;

        $html = $this->fetchAjaxResults('/mz.php', [
            'params[singleSelect]' => '1',
            'params[selectie]' => $criteria->selection ?? 'all',
            'params[a]' => 'check',
            'params[c]' => (string) $criteria->perPage,
            'params[order]' => ($criteria->sortOrder ?? SortOrder::Default)->value,
            'params[layout]' => 'line',
            'params[every]' => '10,6',
            'params[nr]' => 'true',
            'params[s]' => (string) $offset,
            'params[max]' => (string) $criteria->perPage,
            't' => (string) $this->now(),
        ]);

        $results = $this->parser->parseResultsHtml($html);
        $totalCount = $this->parser->parseTotalCount($html);

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
        /** @var Category[]|null $cached */
        $cached = $this->cache?->get('motor-occasion:categories');

        if ($cached !== null) {
            return $cached;
        }

        $this->ensureSession();

        if ($this->categories !== null) {
            $this->cache?->set('motor-occasion:categories', $this->categories, $this->cacheTtl);

            return $this->categories;
        }

        if ($this->homepageHtml === null) {
            throw new MotorOccasionException('Homepage HTML not available for category parsing');
        }

        $this->categories = $this->parser->parseCategoriesFromHomepage($this->homepageHtml);

        $this->cache?->set('motor-occasion:categories', $this->categories, $this->cacheTtl);

        return $this->categories;
    }

    /**
     * @throws MotorOccasionException
     */
    public function getOffers(int $page = 1, int $perPage = 40, ?SortOrder $sortOrder = null): SearchResult
    {
        $this->ensureSession();

        $offset = ($page - 1) * $perPage;

        $html = $this->fetchAjaxResults('/ms.php', [
            'params[order]' => ($sortOrder ?? SortOrder::RecentlyUpdated)->value,
            'params[max]' => (string) $perPage,
            'params[layout]' => 'big',
            'params[every]' => '10,8',
            'params[s]' => (string) $offset,
            'params[c]' => (string) $perPage,
            'params[nr]' => 'true',
            't' => (string) $this->now(),
        ]);

        $results = $this->parser->parseOffersHtml($html);
        $totalCount = $this->parser->parseTotalCount($html);

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

        try {
            $response = $this->httpClient->request('GET', $result->url, [
                'cookies' => $this->cookieJar,
            ]);
        } catch (GuzzleException $guzzleException) {
            throw new MotorOccasionException('HTTP request failed for detail page: ' . $guzzleException->getMessage(), previous: $guzzleException);
        }

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not fetch detail page (HTTP ' . $response->getStatusCode() . ')');
        }

        $html = $response->getBody()->getContents();

        return $this->parser->parseDetailHtml($html, $result->url);
    }

    /**
     * Fetch high-resolution image URLs for a listing.
     *
     * Loads the detail page and extracts all gallery images at the highest
     * available resolution (typically 1024x768). This is useful when you
     * need high-quality images from search results without fetching the
     * full listing detail.
     *
     * @return string[]
     *
     * @throws MotorOccasionException
     */
    public function getImages(Result $result): array
    {
        $this->ensureSession();

        try {
            $response = $this->httpClient->request('GET', $result->url, [
                'cookies' => $this->cookieJar,
            ]);
        } catch (GuzzleException $guzzleException) {
            throw new MotorOccasionException('HTTP request failed for detail page: ' . $guzzleException->getMessage(), previous: $guzzleException);
        }

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not fetch detail page (HTTP ' . $response->getStatusCode() . ')');
        }

        $html = $response->getBody()->getContents();

        return $this->parser->parseImagesFromDetailHtml($html);
    }

    /**
     * Force a fresh session on the next request.
     *
     * Clears in-memory state and invalidates cached brands/categories
     * in the external PSR-16 cache (if configured).
     */
    public function resetSession(): void
    {
        $this->clearSession();

        $this->cache?->delete('motor-occasion:brands');
        $this->cache?->delete('motor-occasion:categories');
    }

    /**
     * Clear in-memory session state without invalidating the external cache.
     *
     * Used internally by search() to reset server-side search params
     * without wiping cached brands/categories.
     */
    private function clearSession(): void
    {
        $this->cookieJar = new CookieJar();
        $this->homepageHtml = null;
        $this->categories = null;
    }

    /**
     * @param array<string, string> $query
     *
     * @throws MotorOccasionException
     */
    private function fetchAjaxResults(string $endpoint, array $query): string
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . $endpoint, [
                'cookies' => $this->cookieJar,
                'query' => $query,
                'headers' => [
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            ]);
        } catch (GuzzleException $guzzleException) {
            throw new MotorOccasionException('HTTP request failed for ' . $endpoint . ': ' . $guzzleException->getMessage(), previous: $guzzleException);
        }

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not fetch AJAX results from ' . $endpoint . ' (HTTP ' . $response->getStatusCode() . ')');
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

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL, [
                'cookies' => $this->cookieJar,
            ]);
        } catch (GuzzleException $guzzleException) {
            throw new MotorOccasionException('HTTP request failed while establishing session: ' . $guzzleException->getMessage(), previous: $guzzleException);
        }

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not retrieve session from motoroccasion.nl (HTTP ' . $response->getStatusCode() . ')');
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

        if ($criteria->license instanceof LicenseCategory) {
            $params['rbw'] = $criteria->license->value;
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
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/mz.php', [
                'cookies' => $this->cookieJar,
                'query' => [
                    'params[' . $key . ']' => $value,
                    'params[a]' => 'check',
                ],
            ]);
        } catch (GuzzleException $guzzleException) {
            throw new MotorOccasionException('HTTP request failed while setting search parameter ' . $key . ': ' . $guzzleException->getMessage(), previous: $guzzleException);
        }

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not set search parameter: ' . $key . ' (HTTP ' . $response->getStatusCode() . ')');
        }
    }

    /**
     * @throws MotorOccasionException
     */
    private function fetchSearchForm(): string
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/fs.php', [
                'cookies' => $this->cookieJar,
                'query' => ['s' => 'mz'],
            ]);
        } catch (GuzzleException $guzzleException) {
            throw new MotorOccasionException('HTTP request failed while fetching search form: ' . $guzzleException->getMessage(), previous: $guzzleException);
        }

        if ($response->getStatusCode() !== 200) {
            throw new MotorOccasionException('Could not fetch search form (HTTP ' . $response->getStatusCode() . ')');
        }

        return $response->getBody()->getContents();
    }

    private function now(): int
    {
        if ($this->clock instanceof Closure) {
            return ($this->clock)();
        }

        return time();
    }
}
