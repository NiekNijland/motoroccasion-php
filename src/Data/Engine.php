<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

readonly class Engine
{
    public function __construct(
        public ?int $capacityCc = null,
        public ?string $type = null,
        public ?int $cylinders = null,
        public ?int $valves = null,
        public ?string $boreAndStroke = null,
        public ?string $compressionRatio = null,
        public ?string $fuelDelivery = null,
        public ?string $fuelType = null,
        public ?bool $isElectric = null,
        public ?string $ignition = null,
        public ?string $maxTorque = null,
        public ?string $clutch = null,
        public ?string $gearbox = null,
        public ?string $driveType = null,
        public ?string $starter = null,
        public ?string $topSpeed = null,
    ) {
    }

    /**
     * @param array{capacityCc?: ?int, type?: ?string, cylinders?: ?int, valves?: ?int, boreAndStroke?: ?string, compressionRatio?: ?string, fuelDelivery?: ?string, fuelType?: ?string, isElectric?: ?bool, ignition?: ?string, maxTorque?: ?string, clutch?: ?string, gearbox?: ?string, driveType?: ?string, starter?: ?string, topSpeed?: ?string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            capacityCc: $data['capacityCc'] ?? null,
            type: $data['type'] ?? null,
            cylinders: $data['cylinders'] ?? null,
            valves: $data['valves'] ?? null,
            boreAndStroke: $data['boreAndStroke'] ?? null,
            compressionRatio: $data['compressionRatio'] ?? null,
            fuelDelivery: $data['fuelDelivery'] ?? null,
            fuelType: $data['fuelType'] ?? null,
            isElectric: $data['isElectric'] ?? null,
            ignition: $data['ignition'] ?? null,
            maxTorque: $data['maxTorque'] ?? null,
            clutch: $data['clutch'] ?? null,
            gearbox: $data['gearbox'] ?? null,
            driveType: $data['driveType'] ?? null,
            starter: $data['starter'] ?? null,
            topSpeed: $data['topSpeed'] ?? null,
        );
    }

    /**
     * @return array{capacityCc: ?int, type: ?string, cylinders: ?int, valves: ?int, boreAndStroke: ?string, compressionRatio: ?string, fuelDelivery: ?string, fuelType: ?string, isElectric: ?bool, ignition: ?string, maxTorque: ?string, clutch: ?string, gearbox: ?string, driveType: ?string, starter: ?string, topSpeed: ?string}
     */
    public function toArray(): array
    {
        return [
            'capacityCc' => $this->capacityCc,
            'type' => $this->type,
            'cylinders' => $this->cylinders,
            'valves' => $this->valves,
            'boreAndStroke' => $this->boreAndStroke,
            'compressionRatio' => $this->compressionRatio,
            'fuelDelivery' => $this->fuelDelivery,
            'fuelType' => $this->fuelType,
            'isElectric' => $this->isElectric,
            'ignition' => $this->ignition,
            'maxTorque' => $this->maxTorque,
            'clutch' => $this->clutch,
            'gearbox' => $this->gearbox,
            'driveType' => $this->driveType,
            'starter' => $this->starter,
            'topSpeed' => $this->topSpeed,
        ];
    }
}
