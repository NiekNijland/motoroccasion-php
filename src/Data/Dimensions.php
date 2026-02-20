<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class Dimensions
{
    public function __construct(
        public ?int $seatHeightMm = null,
        public ?int $wheelbaseMm = null,
        public ?int $lengthMm = null,
        public ?int $widthMm = null,
        public ?int $heightMm = null,
        public ?float $tankCapacityLiters = null,
        public ?int $weightKg = null,
    ) {
    }

    /**
     * @param array{seatHeightMm?: ?int, wheelbaseMm?: ?int, lengthMm?: ?int, widthMm?: ?int, heightMm?: ?int, tankCapacityLiters?: ?float, weightKg?: ?int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            seatHeightMm: $data['seatHeightMm'] ?? null,
            wheelbaseMm: $data['wheelbaseMm'] ?? null,
            lengthMm: $data['lengthMm'] ?? null,
            widthMm: $data['widthMm'] ?? null,
            heightMm: $data['heightMm'] ?? null,
            tankCapacityLiters: $data['tankCapacityLiters'] ?? null,
            weightKg: $data['weightKg'] ?? null,
        );
    }

    /**
     * @return array{seatHeightMm: ?int, wheelbaseMm: ?int, lengthMm: ?int, widthMm: ?int, heightMm: ?int, tankCapacityLiters: ?float, weightKg: ?int}
     */
    public function toArray(): array
    {
        return [
            'seatHeightMm' => $this->seatHeightMm,
            'wheelbaseMm' => $this->wheelbaseMm,
            'lengthMm' => $this->lengthMm,
            'widthMm' => $this->widthMm,
            'heightMm' => $this->heightMm,
            'tankCapacityLiters' => $this->tankCapacityLiters,
            'weightKg' => $this->weightKg,
        ];
    }
}
