<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Dto\Geo\CityDto;
use App\Dto\Geo\CountryDto;
use App\Service\Geo\GeoDbClientInterface;
use Symfony\Component\HttpFoundation\Request;

class GeoSearchService
{
    public function __construct(private readonly GeoDbClientInterface $geoDbClient)
    {
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function searchCountries(Request $request): array
    {
        $query = (string) $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 10);

        return array_map(
            static fn (CountryDto $dto): array => $dto->toArray(),
            $this->geoDbClient->searchCountries($query, $limit),
        );
    }

    /**
     * @return array<int, array{id: int, name: string, countryCode: string, regionName: ?string, latitude: ?float, longitude: ?float}>
     */
    public function searchCities(Request $request): array
    {
        $query = (string) $request->query->get('q', '');
        $country = (string) $request->query->get('country', '');
        $limit = (int) $request->query->get('limit', 10);

        return array_map(
            static fn (CityDto $dto): array => $dto->toArray(),
            $this->geoDbClient->searchCities($query, $country, $limit),
        );
    }
}
