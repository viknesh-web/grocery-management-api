<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AddressService
{
    private const CACHE_TTL = 86400; // 24 hours

    public function searchUAEAreas(string $query): array
    {
        $query = trim($query);

        if (strlen($query) < 2) {
            return [];
        }

        $cacheKey = 'uae_address_search:' . md5($query);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($query) {
            return $this->fetchFromGeoapify($query);
        });
    }

    private function fetchFromGeoapify(string $query): array
    {
        $apiKey = config('services.geoapify.key');

        if (!$apiKey) {
            return [];
        }

        $url = 'https://api.geoapify.com/v1/geocode/autocomplete?' . http_build_query([
            'text' => $query,
            'filter' => 'countrycode:ae',
            'limit' => 20,
            'apiKey' => $apiKey
        ]);

        $response = @file_get_contents($url);
        if (!$response) {
            return [];
        }

        $json = json_decode($response, true);
        $features = $json['features'] ?? [];

        return collect($features)
            ->map(fn ($f) => $this->mapFeature($f))
            ->filter(fn ($item) => !empty($item['searchable_text']))
            ->unique(fn ($item) => md5($item['searchable_text']))
            ->values()
            ->toArray();
    }

    private function mapFeature(array $feature): array
    {
        $p = $feature['properties'] ?? [];

        $house = $p['housenumber'] ?? null;
        $street = $p['street'] ?? null;
        $building = $p['building'] ?? null;

        $area = $p['suburb']
            ?? $p['district']
            ?? $p['neighbourhood']
            ?? $p['city_district']
            ?? $p['city']
            ?? null;

        $city = $p['city'] ?? null;
        $emirate = $p['state'] ?? null;
        $full = $p['formatted'] ?? null;

        $addressLine = array_filter([$house, $street, $building]);
        $displayArea = $area;

        if ($addressLine) {
            $displayArea = implode(' ', $addressLine) . ', ' . $area;
        }

        return [
            'area' => $displayArea,
            'area_base' => $area,
            'city' => $city,
            'emirate' => $emirate,
            'street' => $street,
            'full_address' => $full,

            // 🔑 SEARCH TARGET
            'searchable_text' => strtolower(implode(' ', array_filter([
                $displayArea,
                $area,
                $city,
                $emirate,
                $street,
                $full
            ])))
        ];
    }
}
