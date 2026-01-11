<?php

declare(strict_types=1);

namespace App\Service\Geo;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeoDbClient implements GeoDbClientInterface
{
    private const CACHE_TTL_SECONDS = 21600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $rapidApiKey,
        private readonly string $rapidApiHost,
        private readonly string $baseUrl,
    ) {
    }

    public function searchCountries(string $q, int $limit = 10): array
    {
        $query = trim($q);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $limit = $this->clampLimit($limit);
        $cacheKey = $this->buildCountriesCacheKey($query, $limit);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $limit): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $this->fetchCountries($query, $limit);
        });
    }

    public function searchCities(string $q, string $countryCode, int $limit = 10): array
    {
        $query = trim($q);
        $country = strtoupper(trim($countryCode));

        if (mb_strlen($query) < 2 || $country === '') {
            return [];
        }

        $limit = $this->clampLimit($limit);
        $cacheKey = $this->buildCitiesCacheKey($country, $query, $limit);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $country, $limit): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $this->fetchCities($query, $country, $limit);
        });
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    private function fetchCountries(string $query, int $limit): array
    {
        $data = $this->request('countries', [
            'namePrefix' => $query,
            'limit' => $limit,
        ]);

        $items = $data['data'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $code = isset($item['code']) ? (string) $item['code'] : '';
            $name = isset($item['name']) ? (string) $item['name'] : '';

            if ($code === '' || $name === '') {
                continue;
            }

            $results[] = [
                'code' => $code,
                'name' => $name,
            ];
        }

        return $results;
    }

    /**
     * @return array<int, array{id: int, name: string, region: string, countryCode: string, latitude: float, longitude: float}>
     */
    private function fetchCities(string $query, string $countryCode, int $limit): array
    {
        $data = $this->request('cities', [
            'namePrefix' => $query,
            'countryIds' => $countryCode,
            'types' => 'CITY',
            'limit' => $limit,
            'sort' => '-population',
        ]);

        $items = $data['data'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = isset($item['id']) ? (int) $item['id'] : 0;
            $name = isset($item['name']) ? (string) $item['name'] : '';
            $region = isset($item['region']) ? (string) $item['region'] : (string) ($item['regionName'] ?? '');
            $country = isset($item['countryCode']) ? (string) $item['countryCode'] : '';
            $latitude = isset($item['latitude']) ? (float) $item['latitude'] : 0.0;
            $longitude = isset($item['longitude']) ? (float) $item['longitude'] : 0.0;

            if ($id === 0 || $name === '' || $country === '') {
                continue;
            }

            $results[] = [
                'id' => $id,
                'name' => $name,
                'region' => $region,
                'countryCode' => $country,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        return $results;
    }

    /**
     * @param array<string, scalar> $query
     * @return array<string, mixed>
     */
    private function request(string $path, array $query): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'X-RapidAPI-Key' => $this->rapidApiKey,
                    'X-RapidAPI-Host' => $this->rapidApiHost,
                ],
                'query' => $query,
                'timeout' => 2.5,
            ]);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->warning('GeoDB request failed due to transport error.', [
                'path' => $path,
            ]);

            throw new GeoDbUnavailableException('GeoDB transport error.', 0, $exception);
        }

        $statusCode = $response->getStatusCode();
        $rawBody = $response->getContent(false);

        if ($statusCode === 429 || $statusCode >= 500) {
            $this->logger->warning('GeoDB service unavailable.', [
                'status' => $statusCode,
                'path' => $path,
            ]);

            throw new GeoDbUnavailableException('GeoDB unavailable.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->warning('GeoDB request returned non-success status.', [
                'status' => $statusCode,
                'path' => $path,
            ]);

            return [];
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->warning('GeoDB response was not valid JSON.', [
                'path' => $path,
            ]);

            return [];
        }

        return $data;
    }

    private function clampLimit(int $limit): int
    {
        if ($limit < 1) {
            return 1;
        }

        if ($limit > 10) {
            return 10;
        }

        return $limit;
    }

    private function buildCountriesCacheKey(string $query, int $limit): string
    {
        return sprintf('geodb:countries:%s:%d', mb_strtolower($query), $limit);
    }

    private function buildCitiesCacheKey(string $countryCode, string $query, int $limit): string
    {
        return sprintf('geodb:cities:%s:%s:%d', $countryCode, mb_strtolower($query), $limit);
    }
}
