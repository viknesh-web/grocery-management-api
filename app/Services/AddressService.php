<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Address Service
 * 
 * Handles all business logic for address operations.
 * 
 * Responsibilities:
 * - Business logic orchestration
 * - Input validation
 * - Cache management (via CacheService)
 * - Data mapping and transformation
 * - Error handling
 * 
 * Does NOT contain:
 * - Direct Geoapify API calls (delegated to GeoapifyService)
 * - Direct cache operations (uses CacheService)
 */
class AddressService
{
    /**
     * Cache TTL in seconds for UAE area searches (24 hours).
     */
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Minimum query length for validation.
     */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Maximum query length for validation.
     */
    private const MAX_QUERY_LENGTH = 100;

    public function __construct(
        private GeoapifyService $geoapifyService
    ) {}

    /**
     * Search UAE areas using Geoapify Autocomplete API.
     * 
     * Handles:
     * - Input validation
     * - Cache management (check cache, store results)
     * - API call coordination (delegated to GeoapifyService)
     * - Data mapping (Geoapify format to application format)
     * - Error handling with fallback to cache
     *
     * @param string $query Search query
     * @return array Array of mapped address results
     * @throws ValidationException If query is invalid
     * @throws ServiceException If API call fails and no cache available
     */
    public function searchUAEAreas(string $query): array
    {
        // Validate and normalize query (business logic - input validation)
        $query = $this->normalizeQuery($query);
        $this->validateQuery($query);

        // Check cache first (business logic - cache management)
        $cacheKey = $this->getCacheKey($query);
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            Log::debug('Address search cache hit', ['query' => $query]);
            return $cached;
        }

        try {
            // Fetch from API (delegated to GeoapifyService)
            $apiResponse = $this->geoapifyService->autocomplete($query, [
                'filter' => 'countrycode:ae',
                'limit' => 20,
            ]);
            
            if (empty($apiResponse)) {
                // Cache empty results to avoid repeated API calls
                Cache::put($cacheKey, [], self::CACHE_TTL);
                return [];
            }

            // Map and process results (business logic - data transformation)
            $mapped = $this->mapGeoapifyResults($apiResponse);

            // Cache results (business logic - cache management)
            Cache::put($cacheKey, $mapped, self::CACHE_TTL);

            Log::info('Address search completed', [
                'query' => $query,
                'results_count' => count($mapped),
            ]);

            return $mapped;
        } catch (ServiceException $e) {
            // Log error
            Log::error('Failed to search UAE areas', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            // Return cached result if available, even if expired (business logic - graceful degradation)
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                Log::info('Returning cached result after API failure', ['query' => $query]);
                return $cached;
            }

            // Re-throw if no cache available
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Unexpected error in address search', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return cached result if available
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                Log::info('Returning cached result after unexpected error', ['query' => $query]);
                return $cached;
            }

            throw new ServiceException('Unable to fetch UAE areas. Please try again later.');
        }
    }

    /**
     * Normalize search query.
     * 
     * Business logic: Prepares query for processing.
     *
     * @param string $query
     * @return string
     */
    protected function normalizeQuery(string $query): string
    {
        return trim($query);
    }

    /**
     * Validate search query.
     * 
     * Business logic: Ensures query meets requirements.
     *
     * @param string $query
     * @return void
     * @throws ValidationException
     */
    protected function validateQuery(string $query): void
    {
        if (empty($query)) {
            throw new ValidationException(
                'Search query cannot be empty',
                ['query' => ['Search query is required']]
            );
        }

        $length = mb_strlen($query);
        
        if ($length < self::MIN_QUERY_LENGTH) {
            throw new ValidationException(
                sprintf('Search query must be at least %d characters long', self::MIN_QUERY_LENGTH),
                ['query' => [sprintf('Search query must be at least %d characters', self::MIN_QUERY_LENGTH)]]
            );
        }

        if ($length > self::MAX_QUERY_LENGTH) {
            throw new ValidationException(
                sprintf('Search query must not exceed %d characters', self::MAX_QUERY_LENGTH),
                ['query' => [sprintf('Search query must not exceed %d characters', self::MAX_QUERY_LENGTH)]]
            );
        }
    }

    /**
     * Get cache key for query.
     * 
     * Business logic: Generates consistent cache key.
     * Delegates to CacheService for consistency.
     *
     * @param string $query
     * @return string
     */
    protected function getCacheKey(string $query): string
    {
        return CacheService::addressSearchKey($query);
    }

    /**
     * Map Geoapify API results to application format.
     * 
     * Business logic: Transforms external API format to internal format.
     *
     * @param array $features Raw Geoapify features array
     * @return array Mapped address results
     */
    protected function mapGeoapifyResults(array $features): array
    {
        return collect($features)
            ->map(function ($feature) {
                $properties = $feature['properties'] ?? [];

                // Extract address components (business logic - data extraction)
                $houseNumber = $properties['housenumber'] ?? null;
                $street = $properties['street'] ?? null;
                $building = $properties['building'] ?? null;
                $apartment = $properties['apartment'] ?? null;

                // Extract area candidates (prefer more specific fields)
                // Business logic: Priority order for area identification
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

                // Build address line (business logic - address formatting)
                $addressLine = $this->buildAddressLine($houseNumber, $street, $building, $apartment);

                // Build display area (business logic - display formatting)
                $displayArea = $this->buildDisplayArea($addressLine, $areaName);

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
            ->filter(fn($item) => !empty($item['area'])) // Business logic - filter empty results
            ->unique('area') // Business logic - remove duplicates
            ->values()
            ->toArray();
    }

    /**
     * Build address line from components.
     * 
     * Business logic: Formats address components into a single line.
     *
     * @param string|null $houseNumber
     * @param string|null $street
     * @param string|null $building
     * @param string|null $apartment
     * @return array Address line components
     */
    protected function buildAddressLine(
        ?string $houseNumber,
        ?string $street,
        ?string $building,
        ?string $apartment
    ): array {
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

        return $addressLine;
    }

    /**
     * Build display area from address line and area name.
     * 
     * Business logic: Creates formatted display string.
     *
     * @param array $addressLine
     * @param string|null $areaName
     * @return string
     */
    protected function buildDisplayArea(array $addressLine, ?string $areaName): string
    {
        if (empty($areaName)) {
            return '';
        }

        if (empty($addressLine)) {
            return $areaName;
        }

        $addressLineStr = implode(' ', $addressLine);
        return trim($addressLineStr . ', ' . $areaName);
    }
}
