<?php

namespace App\Services;

use App\Models\Address;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AddressService
{
    /**
     * Cache TTL in hours for UAE area searches.
     */
    private const CACHE_TTL_HOURS = 24;

    /**
     * Minimum query length for validation.
     */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Maximum query length for validation.
     */
    private const MAX_QUERY_LENGTH = 100;

    /**
     * Search UAE areas using Geoapify Autocomplete API.
     *
     * @param string $query Search query
     * @return array Array of mapped address results
     * @throws \InvalidArgumentException If query is invalid
     * @throws \Exception If API call fails
     */
    public function searchUAEAreas(string $query): array
    {
        // Validate query
        $query = trim($query);
        $this->validateQuery($query);

        // Check cache first
        $cacheKey = $this->getCacheKey($query);
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        try {
            // Fetch from API
            $apiResponse = $this->fetchFromGeoapify($query);
            
            if (empty($apiResponse)) {
                return [];
            }

            // Map and process results
            $mapped = $this->mapGeoapifyResults($apiResponse);

            // Cache results
            Cache::put($cacheKey, $mapped, now()->addHours(self::CACHE_TTL_HOURS));

            return $mapped;
        } catch (\Exception $e) {
            Log::error('Failed to search UAE areas', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            // Return cached result if available, even if expired
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            throw new \Exception('Unable to fetch UAE areas. Please try again later.');
        }
    }

    /**
     * Validate search query.
     *
     * @param string $query
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateQuery(string $query): void
    {
        if (empty($query)) {
            throw new \InvalidArgumentException('Search query cannot be empty');
        }

        $length = mb_strlen($query);
        
        if ($length < self::MIN_QUERY_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Search query must be at least %d characters long', self::MIN_QUERY_LENGTH)
            );
        }

        if ($length > self::MAX_QUERY_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Search query must not exceed %d characters', self::MAX_QUERY_LENGTH)
            );
        }
    }

    /**
     * Get cache key for query.
     *
     * @param string $query
     * @return string
     */
    private function getCacheKey(string $query): string
    {
        return 'uae_areas:' . md5(strtolower(trim($query)));
    }

    /**
     * Fetch data from Geoapify API.
     *
     * @param string $query
     * @return array
     * @throws \Exception
     */
    private function fetchFromGeoapify(string $query): array
    {
        $apiKey = config('services.geoapify.key');
        $baseUrl = config('services.geoapify.base_url');

        if (empty($apiKey)) {
            throw new \Exception('GEOAPIFY_API_KEY not configured');
        }

        if (empty($baseUrl)) {
            throw new \Exception('Geoapify base URL not configured');
        }

        // Build URL with query parameters
        $params = http_build_query([
            'text' => $query,
            'apiKey' => $apiKey,
            'filter' => 'countrycode:ae',
            'limit' => 20,
        ]);

        $url = $baseUrl . '?' . $params;

        // Initialize cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if ($curlError) {
            throw new \Exception("cURL error: {$curlError}");
        }

        // Handle HTTP errors
        if ($httpCode !== 200) {
            throw new \Exception("API returned HTTP status code: {$httpCode}");
        }

        // Parse JSON response
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from API: ' . json_last_error_msg());
        }

        return $data['features'] ?? [];
    }

    /**
     * Map Geoapify API results to application format.
     *
     * @param array $features
     * @return array
     */
    private function mapGeoapifyResults(array $features): array
    {
        return collect($features)
            ->map(function ($feature) {
                $properties = $feature['properties'] ?? [];

                // Extract address components
                $houseNumber = $properties['housenumber'] ?? null;
                $street = $properties['street'] ?? null;
                $building = $properties['building'] ?? null;
                $apartment = $properties['apartment'] ?? null;

                // Extract area candidates (prefer more specific fields)
                $areaCandidate = $properties['suburb']
                    ?? $properties['district']
                    ?? $properties['neighbourhood']
                    ?? $properties['quarter']
                    ?? $properties['city_district']
                    ?? null;

                $city = $properties['city'] ?? null;
                $emirate = $properties['state'] ?? null;
                $fullAddress = $properties['formatted'] ?? null;
                $areaName = $areaCandidate ?? $city ?? $fullAddress;

                // Build address line
                $addressLine = [];
                if ($houseNumber) {
                    $addressLine[] = $houseNumber;
                }
                if ($street) {
                    $addressLine[] = $street;
                }
                if ($building) {
                    $addressLine[] = $building;
                }
                if ($apartment) {
                    $addressLine[] = 'Apt ' . $apartment;
                }

                $displayArea = $areaName;
                if (!empty($addressLine)) {
                    $addressLineStr = implode(' ', $addressLine);
                    $displayArea = trim($addressLineStr . ', ' . $displayArea);
                }

                return [
                    'area' => $displayArea,
                    'city' => $city,
                    'emirate' => $emirate,
                    'full_address' => $fullAddress,
                    'street' => $street,
                    'house_number' => $houseNumber,
                    'building' => $building,
                    'apartment' => $apartment,
                    'area_base' => $areaName,
                ];
            })
            ->filter(fn($item) => !empty($item['area']))
            ->unique('area')
            ->values()
            ->toArray();
    }
}