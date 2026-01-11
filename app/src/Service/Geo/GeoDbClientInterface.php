<?php

declare(strict_types=1);

namespace App\Service\Geo;

interface GeoDbClientInterface
{
    /**
     * @return array<int, \App\Dto\Geo\CountryDto>
     */
    public function searchCountries(string $q, int $limit = 10): array;

    /**
     * @return array<int, \App\Dto\Geo\CityDto>
     */
    public function searchCities(string $q, string $countryCode, int $limit = 10): array;
}
