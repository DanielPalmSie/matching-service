<?php

declare(strict_types=1);

namespace App\Service\Geo;

interface GeoDbClientInterface
{
    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function searchCountries(string $q, int $limit = 10): array;

    /**
     * @return array<int, array{id: int, name: string, region: string, countryCode: string, latitude: float, longitude: float}>
     */
    public function searchCities(string $q, string $countryCode, int $limit = 10): array;
}
