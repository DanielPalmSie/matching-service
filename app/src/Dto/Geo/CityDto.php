<?php

declare(strict_types=1);

namespace App\Dto\Geo;

readonly class CityDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $countryCode,
        public ?string $regionName,
        public ?float $latitude,
        public ?float $longitude,
    ) {
    }

    /**
     * @return array{id: int, name: string, countryCode: string, regionName: ?string, latitude: ?float, longitude: ?float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'countryCode' => $this->countryCode,
            'regionName' => $this->regionName,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
