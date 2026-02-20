<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class Chassis
{
    public function __construct(
        public ?bool $abs = null,
        public ?string $frameType = null,
        public ?string $frontSuspension = null,
        public ?string $rearSuspension = null,
        public ?string $frontBrake = null,
        public ?string $rearBrake = null,
        public ?string $frontTire = null,
        public ?string $rearTire = null,
    ) {
    }

    /**
     * @param array{abs?: ?bool, frameType?: ?string, frontSuspension?: ?string, rearSuspension?: ?string, frontBrake?: ?string, rearBrake?: ?string, frontTire?: ?string, rearTire?: ?string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            abs: $data['abs'] ?? null,
            frameType: $data['frameType'] ?? null,
            frontSuspension: $data['frontSuspension'] ?? null,
            rearSuspension: $data['rearSuspension'] ?? null,
            frontBrake: $data['frontBrake'] ?? null,
            rearBrake: $data['rearBrake'] ?? null,
            frontTire: $data['frontTire'] ?? null,
            rearTire: $data['rearTire'] ?? null,
        );
    }

    /**
     * @return array{abs: ?bool, frameType: ?string, frontSuspension: ?string, rearSuspension: ?string, frontBrake: ?string, rearBrake: ?string, frontTire: ?string, rearTire: ?string}
     */
    public function toArray(): array
    {
        return [
            'abs' => $this->abs,
            'frameType' => $this->frameType,
            'frontSuspension' => $this->frontSuspension,
            'rearSuspension' => $this->rearSuspension,
            'frontBrake' => $this->frontBrake,
            'rearBrake' => $this->rearBrake,
            'frontTire' => $this->frontTire,
            'rearTire' => $this->rearTire,
        ];
    }
}
