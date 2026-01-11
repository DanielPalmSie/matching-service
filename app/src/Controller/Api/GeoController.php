<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Api\GeoSearchService;
use App\Service\Geo\GeoDbUnavailableException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GeoController
{
    public function __construct(private readonly GeoSearchService $geoSearchService)
    {
    }

    // TODO: Add a per-IP rate limiter (10/min) for the GeoDB endpoints once configured.

    #[Route('/api/geo/countries', name: 'api_geo_countries', methods: ['GET'])]
    public function countries(Request $request): JsonResponse
    {
        try {
            $results = $this->geoSearchService->searchCountries($request);
        } catch (GeoDbUnavailableException) {
            return new JsonResponse(['error' => 'geo_service_unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse($results);
    }

    #[Route('/api/geo/cities', name: 'api_geo_cities', methods: ['GET'])]
    public function cities(Request $request): JsonResponse
    {
        try {
            $results = $this->geoSearchService->searchCities($request);
        } catch (GeoDbUnavailableException) {
            return new JsonResponse(['error' => 'geo_service_unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse($results);
    }
}
