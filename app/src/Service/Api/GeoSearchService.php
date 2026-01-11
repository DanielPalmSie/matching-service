<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Service\Geo\GeoDbClientInterface;
use Symfony\Component\HttpFoundation\Request;

class GeoSearchService
{
    public function __construct(private readonly GeoDbClientInterface $geoDbClient)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchCountries(Request $request): array
    {
        $query = (string) $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 10);

        return $this->geoDbClient->searchCountries($query, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchCities(Request $request): array
    {
        $query = (string) $request->query->get('q', '');
        $country = (string) $request->query->get('country', '');
        $limit = (int) $request->query->get('limit', 10);

        return $this->geoDbClient->searchCities($query, $country, $limit);
    }
}
