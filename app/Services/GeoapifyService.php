<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Geoapify Service
 * 
 */
class GeoapifyService
{
    /**
     * Default timeout for API requests (seconds).
     */
    private const DEFAULT_TIMEOUT = 10;

    /**
     * Default connection timeout for API requests (seconds).
     */
    private const DEFAULT_CONNECT_TIMEOUT = 5;

    /**
     * Default result limit for autocomplete.
     */
    private const DEFAULT_LIMIT = 20;

    /**
     * Search for addresses using Geoapify Autocomplete API. 
     */
    public function autocomplete(string $query, array $options = []): array
    {
        $this->validateConfiguration();

        $apiKey = config('services.geoapify.key');
        $baseUrl = config('services.geoapify.base_url', 'https://api.geoapify.com/v1/geocode/autocomplete');

        $params = array_merge([
            'text' => $query,
            'apiKey' => $apiKey,
            'filter' => $options['filter'] ?? 'countrycode:ae', // Default to UAE
            'limit' => $options['limit'] ?? self::DEFAULT_LIMIT,
        ], $options);

        try {
            $response = Http::timeout(self::DEFAULT_TIMEOUT)
                ->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ])
                ->get($baseUrl, $params);

            if (!$response->successful()) {
                $this->handleHttpError($response->status(), $response->body(), $query);
            }

            $data = $response->json();

            if ($data === null) {
                throw new ServiceException('Invalid JSON response from Geoapify API');
            }

            return $data['features'] ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Geoapify API connection error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            throw new ServiceException('Unable to connect to Geoapify API. Please try again later.');
        } catch (\Exception $e) {
            Log::error('Geoapify API error', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ServiceException('Failed to fetch addresses from Geoapify API. Please try again later.');
        }
    }

    public function validateConfiguration(): void
    {
        $apiKey = config('services.geoapify.key');
        $baseUrl = config('services.geoapify.base_url');

        if (empty($apiKey)) {
            throw new ServiceException('Geoapify API key not configured. Please set GEOAPIFY_API_KEY in your environment.');
        }

        if (empty($baseUrl)) {
            throw new ServiceException('Geoapify base URL not configured. Please set GEOAPIFY_BASE_URL in your environment.');
        }
    }

    public function isConfigured(): bool
    {
        return !empty(config('services.geoapify.key')) &&
               !empty(config('services.geoapify.base_url'));
    }

    protected function handleHttpError(int $statusCode, string $responseBody, string $query): void
    {
        $errorMessage = match ($statusCode) {
            400 => 'Invalid request to Geoapify API. Please check your search query.',
            401 => 'Geoapify API authentication failed. Please check your API key.',
            403 => 'Geoapify API access forbidden. Please check your API key permissions.',
            429 => 'Geoapify API rate limit exceeded. Please try again later.',
            500, 502, 503, 504 => 'Geoapify API is temporarily unavailable. Please try again later.',
            default => "Geoapify API returned an error (HTTP {$statusCode}). Please try again later.",
        };

        Log::error('Geoapify API HTTP error', [
            'status_code' => $statusCode,
            'query' => $query,
            'response_body' => $responseBody,
        ]);

        throw new ServiceException($errorMessage);
    }
}

