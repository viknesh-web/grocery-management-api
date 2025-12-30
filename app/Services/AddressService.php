<?php

namespace App\Services;

use App\Models\Address;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AddressService
{
    /**
     * Get all available addresses.
     */
    public function getDropdownOptions(): array
    {
        $cacheKey = 'addresses_dropdown_options';
        $cacheTtl = config('features.address_api.cache_ttl', 86400);

        return Cache::remember($cacheKey, $cacheTtl, function () {
            $addresses = $this->mergeAddresses();
            return $addresses->pluck('area_name')->sort()->values()->toArray();
        });
    }

    /**
     * Get addresses from database.
     */
    public function getAddressesFromDatabase()
    {
        return Address::where('is_active', true)
            ->orderBy('area_name')
            ->get();
    }

    /**
     * Get addresses from third-party API.
     */
    public function getAddressesFromAPI(): array
    {
        $apiUrl = config('features.address_api.url');
        $apiKey = config('features.address_api.key');

        if (empty($apiUrl) || empty($apiKey)) {
            return [];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? $data ?? [];
            }
        } catch (\Exception $e) {
            Log::warning('Address API request failed', [
                'error' => $e->getMessage(),
                'url' => $apiUrl,
            ]);
        }

        return [];
    }

    /**
     * Merge addresses from database and API.
     */
    public function mergeAddresses()
    {
        $dbAddresses = $this->getAddressesFromDatabase();
        $apiAddresses = $this->getAddressesFromAPI();

        $merged = $dbAddresses->map(function ($address) {
            return [
                'area_name' => $address->area_name,
                'source' => 'database',
            ];
        });

        $existingNames = $merged->pluck('area_name')->toArray();

        foreach ($apiAddresses as $apiAddress) {
            $areaName = is_array($apiAddress) ? ($apiAddress['name'] ?? $apiAddress['area_name'] ?? null) : $apiAddress;

            if ($areaName && !in_array($areaName, $existingNames)) {
                $merged->push([
                    'area_name' => $areaName,
                    'source' => 'api',
                ]);
                $existingNames[] = $areaName;
            }
        }

        return $merged->unique('area_name')->values();
    }

    /**
     * Clear address cache.
     */
    public function clearCache(): void
    {
        Cache::forget('addresses_dropdown_options');
        
        $keys = Cache::get('uae_area_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('uae_area_keys');
    }

    /**
     * Search UAE areas using Geoapify Autocomplete API.
     * Using cURL for direct control.
     */
    public function searchUAEAreas(string $query): array
    {

        $cacheKey = 'uae_areas:' . md5($query);
            
        try {
            $apiKey = env('GEOAPIFY_API_KEY');
            
            if (empty($apiKey)) {
                throw new \Exception("GEOAPIFY_API_KEY not configured");
                return [];
            }
            
            $baseUrl = 'https://api.geoapify.com/v1/geocode/autocomplete';
            $params = http_build_query([
                'text' => $query,
                'apiKey' => $apiKey,
                'filter' => 'countrycode:ae',
                'limit' => 20
            ]);
            
            $url = $baseUrl . '?' . $params;
            
            $ch = curl_init();
            curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Accept-Language: en'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            

            if ($error) {
                Log::error('cURL Error: ' . $error);
                return [];
            }
            
            if ($httpCode !== 200) {
                Log::error('Non-200 response: ' . $httpCode);
                Log::error('Response body: ' . $response);
                return [];
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON decode error: ' . json_last_error_msg());
                return [];
            }
            
            // Geoapify returns results in 'features' array
            $results = $data['features'] ?? [];
            
            if (empty($results)) {
                return [];
            }
            
            // Map Geoapify results to match existing structure
            $mapped = collect($results)->map(function ($feature) {
                $properties = $feature['properties'] ?? [];

                // Extract address components
                $houseNumber = $properties['housenumber'] ?? null;
                $street = $properties['street'] ?? null;
                $building = $properties['building'] ?? null;
                $apartment = $properties['apartment'] ?? null;
                
                // Extract candidates for area: prefer more specific fields, fallback to city or formatted address
                $areaCandidate = $properties['suburb'] ?? $properties['district'] ?? $properties['neighbourhood'] ?? $properties['quarter'] ?? $properties['city_district'] ?? null;
                $city = $properties['city'] ?? null;
                $emirate = $properties['state'] ?? null;
                $fullAddress = $properties['formatted'] ?? null;

                // Build area name
                $areaName = $areaCandidate ?? $city ?? $fullAddress;
                
                // Build address line with street number, building, apartment, street
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
                
                // Combine area with address details for better searchability and display
                // This ensures street numbers are included in the area field which is searchable
                $displayArea = $areaName;
                if (!empty($addressLine)) {
                    $addressLineStr = implode(' ', $addressLine);
                    // Prepend address details to area for better searchability (e.g., "123 Main St, Dubai Marina")
                    $displayArea = trim($addressLineStr . ', ' . $displayArea);
                }

                return [
                    'area' => $displayArea, // Includes street number for searchability
                    'city' => $city,
                    'emirate' => $emirate,
                    'full_address' => $fullAddress,
                    'street' => $street,
                    'house_number' => $houseNumber,
                    'building' => $building,
                    'apartment' => $apartment,
                    'area_base' => $areaName, // Original area name without street details
                ];
            })->filter(fn($item) => !empty($item['area']))->unique('area')->values()->toArray();
                
            Cache::put($cacheKey, $mapped, now()->addHours(24));
            
            return $mapped;
            
        } catch (\Exception $e) {
            throw new \Exception("Unable to fetch UAE areas");
            return [];
        }
    }
}