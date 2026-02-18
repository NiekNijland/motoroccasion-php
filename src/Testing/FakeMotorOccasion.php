<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\Brand;
use NiekNijland\MotorOccasion\Data\Category;
use NiekNijland\MotorOccasion\Data\ListingDetail;
use NiekNijland\MotorOccasion\Data\Result;
use NiekNijland\MotorOccasion\Data\SearchCriteria;
use NiekNijland\MotorOccasion\Data\SearchResult;
use NiekNijland\MotorOccasion\Data\SortOrder;
use NiekNijland\MotorOccasion\Data\Type;
use NiekNijland\MotorOccasion\Exception\MotorOccasionException;
use NiekNijland\MotorOccasion\MotorOccasionInterface;
use RuntimeException;

/**
 * In-memory fake implementation of MotorOccasionInterface for testing.
 *
 * Seed it with data via the constructor or setter methods, then use it
 * as a drop-in replacement for the real client. All calls are recorded
 * for assertion in tests.
 *
 * ```php
 * $fake = new FakeMotorOccasion(
 *     brands: BrandFactory::makeMany(5),
 *     categories: CategoryFactory::makeMany(3),
 * );
 *
 * $service = new YourService($fake);
 * $service->doSomething();
 *
 * $fake->assertSearchCalledTimes(1);
 * ```
 */
class FakeMotorOccasion implements MotorOccasionInterface
{
    /** @var list<array{method: string, args: array<mixed>}> */
    private array $calls = [];

    /**
     * @param Brand[] $brands Brands to return from getBrands()
     * @param Category[] $categories Categories to return from getCategories()
     * @param array<string, Type[]> $types Types keyed by brand value, returned from getTypesForBrand()
     * @param Result[] $results Results to return from search() and getOffers()
     * @param array<string, ListingDetail> $details Details keyed by result URL, returned from getDetail()
     * @param ?MotorOccasionException $exception If set, all data-fetching methods throw this exception
     */
    public function __construct(
        private array $brands = [],
        private array $categories = [],
        private array $types = [],
        private array $results = [],
        private array $details = [],
        private ?MotorOccasionException $exception = null,
    ) {
    }

    /**
     * @param Brand[] $brands
     */
    public function setBrands(array $brands): self
    {
        $this->brands = $brands;

        return $this;
    }

    /**
     * @param Category[] $categories
     */
    public function setCategories(array $categories): self
    {
        $this->categories = $categories;

        return $this;
    }

    /**
     * @param Type[] $types
     */
    public function setTypesForBrand(Brand $brand, array $types): self
    {
        $this->types[$brand->value] = $types;

        return $this;
    }

    /**
     * @param Result[] $results
     */
    public function setResults(array $results): self
    {
        $this->results = $results;

        return $this;
    }

    public function setDetail(Result $result, ListingDetail $detail): self
    {
        $this->details[$result->url] = $detail;

        return $this;
    }

    /**
     * Configure the fake to throw on all data-fetching method calls.
     */
    public function shouldThrow(MotorOccasionException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    // --- MotorOccasionInterface implementation ---

    /**
     * @return Brand[]
     *
     * @throws MotorOccasionException
     */
    public function getBrands(): array
    {
        $this->recordCall('getBrands');
        $this->throwIfConfigured();

        return $this->brands;
    }

    /**
     * @return Type[]
     *
     * @throws MotorOccasionException
     */
    public function getTypesForBrand(Brand $brand): array
    {
        $this->recordCall('getTypesForBrand', ['brand' => $brand]);
        $this->throwIfConfigured();

        return $this->types[$brand->value] ?? [];
    }

    /**
     * @throws MotorOccasionException
     */
    public function search(SearchCriteria $criteria): SearchResult
    {
        $this->recordCall('search', ['criteria' => $criteria]);
        $this->throwIfConfigured();

        $offset = ($criteria->page - 1) * $criteria->perPage;
        $page = array_slice($this->results, $offset, $criteria->perPage);

        return new SearchResult(
            results: $page,
            totalCount: count($this->results),
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
        $this->recordCall('getCategories');
        $this->throwIfConfigured();

        return $this->categories;
    }

    /**
     * @throws MotorOccasionException
     */
    public function getOffers(int $page = 1, int $perPage = 40, ?SortOrder $sortOrder = null): SearchResult
    {
        $this->recordCall('getOffers', ['page' => $page, 'perPage' => $perPage, 'sortOrder' => $sortOrder]);
        $this->throwIfConfigured();

        $offset = ($page - 1) * $perPage;
        $pageResults = array_slice($this->results, $offset, $perPage);

        return new SearchResult(
            results: $pageResults,
            totalCount: count($this->results),
            currentPage: $page,
            perPage: $perPage,
        );
    }

    /**
     * @throws MotorOccasionException
     */
    public function getDetail(Result $result): ListingDetail
    {
        $this->recordCall('getDetail', ['result' => $result]);
        $this->throwIfConfigured();

        if (! isset($this->details[$result->url])) {
            throw new MotorOccasionException('No fake detail configured for URL: ' . $result->url);
        }

        return $this->details[$result->url];
    }

    /**
     * @return string[]
     *
     * @throws MotorOccasionException
     */
    public function getImages(Result $result): array
    {
        $this->recordCall('getImages', ['result' => $result]);
        $this->throwIfConfigured();

        if (! isset($this->details[$result->url])) {
            throw new MotorOccasionException('No fake detail configured for URL: ' . $result->url);
        }

        return $this->details[$result->url]->images;
    }

    public function resetSession(): void
    {
        $this->recordCall('resetSession');
    }

    // --- Call tracking and assertions ---

    /**
     * @return list<array{method: string, args: array<mixed>}>
     */
    public function getCalls(?string $method = null): array
    {
        if ($method === null) {
            return $this->calls;
        }

        return array_values(
            array_filter($this->calls, fn (array $call): bool => $call['method'] === $method),
        );
    }

    public function callCount(?string $method = null): int
    {
        return count($this->getCalls($method));
    }

    public function wasCalled(string $method): bool
    {
        return $this->callCount($method) > 0;
    }

    public function wasNotCalled(string $method): bool
    {
        return $this->callCount($method) === 0;
    }

    /**
     * Assert a method was called exactly N times.
     *
     * @throws RuntimeException
     */
    public function assertCalledTimes(string $method, int $expected): void
    {
        $actual = $this->callCount($method);

        if ($actual !== $expected) {
            throw new RuntimeException(
                sprintf('Expected %s() to be called %d time(s), but was called %d time(s).', $method, $expected, $actual),
            );
        }
    }

    /**
     * Assert a method was called at least once.
     *
     * @throws RuntimeException
     */
    public function assertCalled(string $method): void
    {
        if ($this->wasNotCalled($method)) {
            throw new RuntimeException(
                sprintf('Expected %s() to be called, but it was never called.', $method),
            );
        }
    }

    /**
     * Assert a method was never called.
     *
     * @throws RuntimeException
     */
    public function assertNotCalled(string $method): void
    {
        $count = $this->callCount($method);

        if ($count > 0) {
            throw new RuntimeException(
                sprintf('Expected %s() to never be called, but it was called %d time(s).', $method, $count),
            );
        }
    }

    public function resetCalls(): void
    {
        $this->calls = [];
    }

    // --- Internals ---

    /**
     * @param array<mixed> $args
     */
    private function recordCall(string $method, array $args = []): void
    {
        $this->calls[] = ['method' => $method, 'args' => $args];
    }

    /**
     * @throws MotorOccasionException
     */
    private function throwIfConfigured(): void
    {
        if ($this->exception instanceof MotorOccasionException) {
            throw $this->exception;
        }
    }
}
