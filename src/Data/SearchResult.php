<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class SearchResult
{
    /**
     * @param  Result[]  $results
     */
    public function __construct(
        public array $results,
        public int $totalCount,
        public int $currentPage = 1,
        public int $perPage = 20,
    ) {}

    public function totalPages(): int
    {
        if ($this->perPage <= 0 || $this->totalCount <= 0) {
            return 1;
        }

        return (int) ceil($this->totalCount / $this->perPage);
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages();
    }
}
