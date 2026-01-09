<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * NormalizesInput Trait
 * 
 * Provides reusable methods for normalizing request input before validation. 
 * @package App\Http\Traits
 * @since 1.0.0
 */
trait NormalizesInput
{
    /**
     * Normalize the request input.   
     */
    protected function normalizeInput(Request $request): void
    {
        // Override in controllers to define normalization rules
    }

    /**
     * Trim a string value if present.   
     */
    protected function normalizeString(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            $dataToMerge[$key] = trim((string) $input[$key]);
        }
    }

    /**
     * Trim and uppercase a string value if present. 
     */
    protected function normalizeUppercaseString(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            $dataToMerge[$key] = strtoupper(trim((string) $input[$key]));
        }
    }

    /**
     * Trim and lowercase a string value if present.
     */
    protected function normalizeLowercaseString(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            $dataToMerge[$key] = strtolower(trim((string) $input[$key]));
        }
    }

    /**
     * Cast to integer if present.
     */
    protected function normalizeInteger(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            $dataToMerge[$key] = (int) $input[$key];
        }
    }

    /**
     * Cast to float if present.
     */
    protected function normalizeFloat(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            $dataToMerge[$key] = (float) $input[$key];
        }
    }

    /**
     * Normalize boolean value.
     */
    protected function normalizeBoolean(array $input, string $key, array &$dataToMerge, $default = null): void
    {
        if (array_key_exists($key, $input)) {
            $dataToMerge[$key] = (bool) $input[$key];
        } elseif ($default !== null) {
            $dataToMerge[$key] = $default;
        }
    }

    /**
     * Normalize nullable integer (can be null).
     */
    protected function normalizeNullableInteger(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input)) {
            if ($input[$key] !== null && $input[$key] !== '') {
                $dataToMerge[$key] = (int) $input[$key];
            } else {
                $dataToMerge[$key] = null;
            }
        }
    }

    /**
     * Normalize nullable float (can be null).
     */
    protected function normalizeNullableFloat(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input)) {
            if ($input[$key] !== null && $input[$key] !== '') {
                $dataToMerge[$key] = (float) $input[$key];
            } else {
                $dataToMerge[$key] = null;
            }
        }
    }

    /**
     * Generate slug from name if slug is not provided.
     */
    protected function normalizeSlug(array $input, string $nameKey, string $slugKey, array &$dataToMerge): void
    {
        // If name exists and slug doesn't, generate slug from name
        if (array_key_exists($nameKey, $input) && $input[$nameKey] !== null && $input[$nameKey] !== '') {
            if (!isset($input[$slugKey]) || empty($input[$slugKey])) {
                $dataToMerge[$slugKey] = Str::slug(trim((string) $input[$nameKey]));
            }
        }

        // Normalize slug if provided
        if (array_key_exists($slugKey, $input) && $input[$slugKey] !== null && $input[$slugKey] !== '') {
            $dataToMerge[$slugKey] = Str::slug(trim((string) $input[$slugKey]));
        }
    }

    /**
     * Normalize array value.
     * 
     */
    protected function normalizeArray(array $input, string $key, array &$dataToMerge, bool $filterEmpty = false): void
    {
        if (array_key_exists($key, $input)) {
            $value = $input[$key];
            
            if (!is_array($value)) {
                $value = $value !== null ? [$value] : [];
            }
            
            if ($filterEmpty) {
                $value = array_filter($value, fn($item) => $item !== null && $item !== '');
            }
            
            $dataToMerge[$key] = array_values($value);
        }
    }

    /**
     * Merge normalized data into request.
     */
    protected function mergeNormalizedData(Request $request, array $dataToMerge): void
    {
        if (!empty($dataToMerge)) {
            $request->merge($dataToMerge);
        }
    }
}
