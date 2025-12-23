<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * NormalizesInput Trait
 * 
 * Provides reusable methods for normalizing request input before validation.
 * Controllers can use this trait and implement normalizeInput() method.
 */
trait NormalizesInput
{
    /**
     * Normalize the request input.
     * Override this method in controllers to define normalization rules.
     *
     * @param Request $request
     * @return void
     */
    protected function normalizeInput(Request $request): void
    {
        // Override in controllers
    }

    /**
     * Trim a string value if present.
     *
     * @param array $input
     * @param string $key
     * @param array $dataToMerge
     * @return void
     */
    protected function normalizeString(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            $dataToMerge[$key] = trim((string) $input[$key]);
        }
    }

    /**
     * Trim and uppercase a string value if present.
     *
     * @param array $input
     * @param string $key
     * @param array $dataToMerge
     * @return void
     */
    protected function normalizeUppercaseString(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            $dataToMerge[$key] = strtoupper(trim((string) $input[$key]));
        }
    }

    /**
     * Trim and lowercase a string value if present.
     *
     * @param array $input
     * @param string $key
     * @param array $dataToMerge
     * @return void
     */
    protected function normalizeLowercaseString(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            $dataToMerge[$key] = strtolower(trim((string) $input[$key]));
        }
    }

    /**
     * Cast to integer if present.
     *
     * @param array $input
     * @param string $key
     * @param array $dataToMerge
     * @return void
     */
    protected function normalizeInteger(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            $dataToMerge[$key] = (int) $input[$key];
        }
    }

    /**
     * Cast to float if present.
     *
     * @param array $input
     * @param string $key
     * @param array $dataToMerge
     * @return void
     */
    protected function normalizeFloat(array $input, string $key, array &$dataToMerge): void
    {
        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            $dataToMerge[$key] = (float) $input[$key];
        }
    }

    /**
     * Normalize boolean value.
     *
     * @param array $input
     * @param string $key
     * @param array $dataToMerge
     * @param mixed $default Default value if key doesn't exist
     * @return void
     */
    protected function normalizeBoolean(array $input, string $key, array &$dataToMerge, $default = null): void
    {
        if (array_key_exists($key, $input)) {
            $value = $input[$key];
            if (is_string($value)) {
                $dataToMerge[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ($default ?? false);
            } else {
                $dataToMerge[$key] = (bool) $value;
            }
        } elseif ($default !== null) {
            $dataToMerge[$key] = $default;
        }
    }

    /**
     * Normalize nullable integer (can be null).
     *
     * @param array $input
     * @param string $key
     * @param array $dataToMerge
     * @return void
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
     * Generate slug from name if slug is not provided.
     *
     * @param array $input
     * @param string $nameKey
     * @param string $slugKey
     * @param array $dataToMerge
     * @return void
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
     * Merge normalized data into request.
     *
     * @param Request $request
     * @param array $dataToMerge
     * @return void
     */
    protected function mergeNormalizedData(Request $request, array $dataToMerge): void
    {
        if (!empty($dataToMerge)) {
            $request->merge($dataToMerge);
        }
    }
}



