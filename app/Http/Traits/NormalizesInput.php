<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * NormalizesInput Trait
 * 
 * Provides reusable methods for normalizing request input before validation.
 * 
 * This trait is useful for controllers that need to normalize input data
 * before validation. It provides common normalization methods for strings,
 * numbers, booleans, and other data types.
 * 
 * **Usage**:
 * 1. Use this trait in your controller
 * 2. Override the `normalizeInput()` method to define normalization rules
 * 3. Call `normalizeInput()` in your controller's methods or use it in FormRequest's `prepareForValidation()`
 * 
 * @package App\Http\Traits
 * @since 1.0.0
 */
trait NormalizesInput
{
    /**
     * Normalize the request input.
     * 
     * Override this method in controllers to define normalization rules.
     * This method is called to normalize input before validation.
     *
     * @param Request $request The HTTP request to normalize
     * @return void
     * 
     * @example
     * ```php
     * protected function normalizeInput(Request $request): void
     * {
     *     $data = [];
     *     $input = $request->all();
     *     
     *     $this->normalizeString($input, 'name', $data);
     *     $this->normalizeInteger($input, 'price', $data);
     *     
     *     $this->mergeNormalizedData($request, $data);
     * }
     * ```
     */
    protected function normalizeInput(Request $request): void
    {
        // Override in controllers to define normalization rules
    }

    /**
     * Trim a string value if present.
     * 
     * Removes leading and trailing whitespace from a string value.
     * Only processes the value if it exists and is not null or empty string.
     *
     * @param array<string, mixed> $input The input array
     * @param string $key The key to normalize
     * @param array<string, mixed> $dataToMerge The array to merge normalized data into (by reference)
     * @return void
     * 
     * @example
     * ```php
     * $this->normalizeString($input, 'name', $data);
     * // If $input['name'] = "  John Doe  ", $data['name'] = "John Doe"
     * ```
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
     * Removes leading and trailing whitespace and converts to uppercase.
     * Only processes the value if it exists and is not null or empty string.
     *
     * @param array<string, mixed> $input The input array
     * @param string $key The key to normalize
     * @param array<string, mixed> $dataToMerge The array to merge normalized data into (by reference)
     * @return void
     * 
     * @example
     * ```php
     * $this->normalizeUppercaseString($input, 'code', $data);
     * // If $input['code'] = "  abc123  ", $data['code'] = "ABC123"
     * ```
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
     * Removes leading and trailing whitespace and converts to lowercase.
     * Only processes the value if it exists and is not null or empty string.
     *
     * @param array<string, mixed> $input The input array
     * @param string $key The key to normalize
     * @param array<string, mixed> $dataToMerge The array to merge normalized data into (by reference)
     * @return void
     * 
     * @example
     * ```php
     * $this->normalizeLowercaseString($input, 'email', $data);
     * // If $input['email'] = "  John@Example.COM  ", $data['email'] = "john@example.com"
     * ```
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
     * Converts a value to an integer. Only processes the value if it exists
     * and is not null or empty string.
     *
     * @param array<string, mixed> $input The input array
     * @param string $key The key to normalize
     * @param array<string, mixed> $dataToMerge The array to merge normalized data into (by reference)
     * @return void
     * 
     * @example
     * ```php
     * $this->normalizeInteger($input, 'quantity', $data);
     * // If $input['quantity'] = "123", $data['quantity'] = 123
     * ```
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
     * Converts a value to a float. Only processes the value if it exists
     * and is not null or empty string.
     *
     * @param array<string, mixed> $input The input array
     * @param string $key The key to normalize
     * @param array<string, mixed> $dataToMerge The array to merge normalized data into (by reference)
     * @return void
     * 
     * @example
     * ```php
     * $this->normalizeFloat($input, 'price', $data);
     * // If $input['price'] = "99.99", $data['price'] = 99.99
     * ```
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
     * Converts a value to a boolean. If the key doesn't exist and a default
     * value is provided, uses the default.
     *
     * @param array<string, mixed> $input The input array
     * @param string $key The key to normalize
     * @param array<string, mixed> $dataToMerge The array to merge normalized data into (by reference)
     * @param mixed $default Default value if key doesn't exist (optional)
     * @return void
     * 
     * @example
     * ```php
     * $this->normalizeBoolean($input, 'is_active', $data);
     * $this->normalizeBoolean($input, 'is_featured', $data, false);
     * ```
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
     * 
     * Converts a value to an integer, or null if the value is null or empty.
     * This is useful for optional integer fields.
     *
     * @param array<string, mixed> $input The input array
     * @param string $key The key to normalize
     * @param array<string, mixed> $dataToMerge The array to merge normalized data into (by reference)
     * @return void
     * 
     * @example
     * ```php
     * $this->normalizeNullableInteger($input, 'category_id', $data);
     * // If $input['category_id'] = "5", $data['category_id'] = 5
     * // If $input['category_id'] = "", $data['category_id'] = null
     * ```
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
     * 
     * Converts a value to a float, or null if the value is null or empty.
     * This is useful for optional float fields.
     *
     * @param array<string, mixed> $input The input array
     * @param string $key The key to normalize
     * @param array<string, mixed> $dataToMerge The array to merge normalized data into (by reference)
     * @return void
     * 
     * @example
     * ```php
     * $this->normalizeNullableFloat($input, 'discount', $data);
     * ```
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
     * 
     * Automatically generates a URL-friendly slug from a name field if
     * the slug field is not provided or is empty. Also normalizes the slug
     * if it is provided.
     *
     * @param array<string, mixed> $input The input array
     * @param string $nameKey The key for the name field
     * @param string $slugKey The key for the slug field
     * @param array<string, mixed> $dataToMerge The array to merge normalized data into (by reference)
     * @return void
     * 
     * @example
     * ```php
     * $this->normalizeSlug($input, 'name', 'slug', $data);
     * // If $input['name'] = "My Product" and slug is empty,
     * // $data['slug'] = "my-product"
     * ```
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
     * Ensures a value is an array. If the value is not an array, it will be
     * converted to an array or set to an empty array.
     *
     * @param array<string, mixed> $input The input array
     * @param string $key The key to normalize
     * @param array<string, mixed> $dataToMerge The array to merge normalized data into (by reference)
     * @param bool $filterEmpty Whether to filter out empty values (default: false)
     * @return void
     * 
     * @example
     * ```php
     * $this->normalizeArray($input, 'tags', $data);
     * $this->normalizeArray($input, 'categories', $data, true);
     * ```
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
     * 
     * Merges the normalized data array into the request object, making it
     * available for validation and further processing.
     *
     * @param Request $request The HTTP request to merge data into
     * @param array<string, mixed> $dataToMerge The normalized data to merge
     * @return void
     * 
     * @example
     * ```php
     * $data = [];
     * $this->normalizeString($input, 'name', $data);
     * $this->normalizeInteger($input, 'price', $data);
     * $this->mergeNormalizedData($request, $data);
     * ```
     */
    protected function mergeNormalizedData(Request $request, array $dataToMerge): void
    {
        if (!empty($dataToMerge)) {
            $request->merge($dataToMerge);
        }
    }
}
