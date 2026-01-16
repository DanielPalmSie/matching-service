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
     * @return array{items: array<int, array<string, mixed>>, limit: int, offset: int, hasMore: bool}
     */
    public function searchCities(Request $request): array
    {
        $query = (string) $request->query->get('q', '');
        $country = (string) $request->query->get('country', '');
        $limit = (int) $request->query->get('limit', 10);
        $offset = (int) $request->query->get('offset', 0);

        $limit = $this->clampLimit($limit);
        $offset = max(0, $offset);

        if (mb_strlen(trim($query)) < 2 || trim($country) === '') {
            return [
                'items' => [],
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => false,
            ];
        }

        $page = $this->geoDbClient->searchCities($query, $country, $limit, $offset);

        /** @var array<int, array<string, mixed>> $rawItems */
        $rawItems = $page['items'];
        $totalCount = $page['totalCount'];
        $rawCount = (int) $page['rawCount'];

        $items = $this->refineCityResults($rawItems, $query, $limit);

        return [
            'items' => $items,
            'limit' => $limit,
            'offset' => $offset,
            'hasMore' => $this->hasMore($offset, $limit, $totalCount, $rawCount),
        ];

    }

    /**
     * @param array<int, mixed> $results
     * @return array<int, array<string, mixed>>
     */
    private function refineCityResults(array $results, string $query, int $limit): array
    {
        $normalizedQuery = $this->normalizeString($query);
        if ($normalizedQuery === '') {
            return array_slice($results, 0, $limit);
        }

        $strictMatches = [];
        $fallbackMatches = [];

        foreach ($results as $city) {
            if (!is_array($city)) {
                continue;
            }

            $name = (string) ($city['name'] ?? '');
            $normalizedName = $this->normalizeString($name);

            if ($normalizedName !== '' && str_starts_with($normalizedName, $normalizedQuery)) {
                $strictMatches[] = $city;
                continue;
            }

            $fallbackMatches[] = $city;
        }

        $merged = array_merge($strictMatches, $fallbackMatches);

        return $this->deduplicateCities($merged, $limit);
    }

    /**
     * @param array<int, mixed> $cities
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateCities(array $cities, int $limit): array
    {
        $deduped = [];
        $seen = [];

        foreach ($cities as $city) {
            if (!is_array($city)) {
                continue;
            }

            /** @var array<string, mixed> $city */
            $key = $this->buildCityDedupKey($city);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $city;

            if (count($deduped) >= $limit) {
                break;
            }
        }

        return $deduped;
    }

    /**
     * @param array<string, mixed> $city
     */
    private function buildCityDedupKey(array $city): string
    {
        $name = $this->normalizeString((string) ($city['name'] ?? ''));
        $region = $this->normalizeString((string) ($city['region'] ?? ''));
        $country = strtoupper(trim((string) ($city['countryCode'] ?? '')));

        if ($name === '' || $country === '') {
            return '';
        }

        return sprintf('%s|%s|%s', $name, $region, $country);
    }

    private function normalizeString(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $collapsed = preg_replace('/\s+/u', ' ', $trimmed);
        $normalized = is_string($collapsed) ? $collapsed : $trimmed;
        $normalized = mb_strtolower($normalized);

        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($transliterated) && $transliterated !== '') {
            $normalized = $transliterated;
        }

        $normalized = preg_replace('/[^a-z0-9\\s]/', '', $normalized);
        $normalized = is_string($normalized) ? $normalized : '';

        return trim($normalized);
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

    private function hasMore(int $offset, int $limit, ?int $totalCount, int $rawCount): bool
    {
        if (is_int($totalCount) && $totalCount >= 0) {
            return ($offset + $limit) < $totalCount;
        }

        return $rawCount >= $limit;
    }
}
