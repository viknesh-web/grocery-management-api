<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Cache Service
 * 
 * Centralized cache management with consistent key naming and TTLs.
 * 
 * This is a utility service providing static methods for:
 * - Cache key generation
 * - Cache clearing operations
 * - Consistent TTL management
 * 
 * All cache operations should use this service for consistency.
 */
class CacheService
{
    // Cache TTLs (in seconds)
    const TTL_SHORT = 300;      // 5 minutes
    const TTL_MEDIUM = 1800;    // 30 minutes
    const TTL_LONG = 3600;      // 1 hour
    const TTL_DAY = 86400;      // 24 hours

    // Cache key prefixes
    const PREFIX_PRODUCT = 'product';
    const PREFIX_CATEGORY = 'category';
    const PREFIX_CUSTOMER = 'customer';
    const PREFIX_PRICE_LIST = 'price_list';
    const PREFIX_ADDRESS = 'address';

    /**
     * Get cache key for product list.
     * 
     * Generates consistent cache key based on filters.
     *
     * @param array $filters Product filters
     * @return string Cache key
     */
    public static function productListKey(array $filters): string
    {
        $filterHash = md5(json_encode($filters));
        return self::PREFIX_PRODUCT . ":list:{$filterHash}";
    }

    /**
     * Get cache key for single product.
     *
     * @param int $id Product ID
     * @return string Cache key
     */
    public static function productKey(int $id): string
    {
        return self::PREFIX_PRODUCT . ":{$id}";
    }

    /**
     * Get cache key for category list.
     * 
     * Generates consistent cache key based on filters.
     *
     * @param array $filters Category filters (optional)
     * @return string Cache key
     */
    public static function categoryListKey(array $filters = []): string
    {
        if (empty($filters)) {
            return self::PREFIX_CATEGORY . ':list:all';
        }
        $filterHash = md5(json_encode($filters));
        return self::PREFIX_CATEGORY . ":list:{$filterHash}";
    }

    /**
     * Get cache key for single category.
     *
     * @param int $id Category ID
     * @return string Cache key
     */
    public static function categoryKey(int $id): string
    {
        return self::PREFIX_CATEGORY . ":{$id}";
    }

    /**
     * Get cache key for customer list.
     * 
     * Generates consistent cache key based on filters.
     *
     * @param array $filters Customer filters (optional)
     * @return string Cache key
     */
    public static function customerListKey(array $filters = []): string
    {
        if (empty($filters)) {
            return self::PREFIX_CUSTOMER . ':list:all';
        }
        $filterHash = md5(json_encode($filters));
        return self::PREFIX_CUSTOMER . ":list:{$filterHash}";
    }

    /**
     * Get cache key for single customer.
     *
     * @param int $id Customer ID
     * @return string Cache key
     */
    public static function customerKey(int $id): string
    {
        return self::PREFIX_CUSTOMER . ":{$id}";
    }

    /**
     * Get cache key for price list PDF.
     * 
     * Generates consistent cache key based on product IDs and layout.
     *
     * @param array $productIds Product IDs (optional)
     * @param string $layout PDF layout ('regular' or 'catalog')
     * @return string Cache key
     */
    public static function priceListKey(array $productIds = [], string $layout = 'regular'): string
    {
        $idsHash = md5(json_encode($productIds));
        return self::PREFIX_PRICE_LIST . ":{$layout}:{$idsHash}";
    }

    /**
     * Get cache key for address search.
     * 
     * Generates consistent cache key based on search query.
     *
     * @param string $query Search query
     * @return string Cache key
     */
    public static function addressSearchKey(string $query): string
    {
        $normalized = strtolower(trim($query));
        $queryHash = md5($normalized);
        return self::PREFIX_ADDRESS . ":uae_areas:{$queryHash}";
    }

    /**
     * Clear all product caches.
     * 
     * Uses cache tags if supported (Redis, Memcached), otherwise clears all cache.
     *
     * @return void
     */
    public static function clearProductCache(): void
    {
        // Try to use tags if supported (Redis, Memcached)
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags([self::PREFIX_PRODUCT])->flush();
        } else {
            // Fallback: clear by pattern (file cache doesn't support tags)
            // Note: This clears all cache for file cache - consider implementing pattern matching if needed
            Cache::flush();
        }
    }

    /**
     * Clear all category caches.
     * 
     * Uses cache tags if supported (Redis, Memcached), otherwise clears all cache.
     *
     * @return void
     */
    public static function clearCategoryCache(): void
    {
        // Try to use tags if supported (Redis, Memcached)
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags([self::PREFIX_CATEGORY])->flush();
        } else {
            // Fallback: clear by pattern
            Cache::flush();
        }
    }

    /**
     * Clear all customer caches.
     * 
     * Uses cache tags if supported (Redis, Memcached), otherwise clears all cache.
     *
     * @return void
     */
    public static function clearCustomerCache(): void
    {
        // Try to use tags if supported (Redis, Memcached)
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags([self::PREFIX_CUSTOMER])->flush();
        } else {
            // Fallback: clear by pattern
            Cache::flush();
        }
    }

    /**
     * Clear all address caches.
     * 
     * Uses cache tags if supported (Redis, Memcached), otherwise clears all cache.
     *
     * @return void
     */
    public static function clearAddressCache(): void
    {
        // Try to use tags if supported (Redis, Memcached)
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags([self::PREFIX_ADDRESS])->flush();
        } else {
            // Fallback: clear by pattern
            Cache::flush();
        }
    }

    /**
     * Clear specific product cache.
     * 
     * Clears both the specific product cache and all product list caches.
     *
     * @param int $id Product ID
     * @return void
     */
    public static function clearProduct(int $id): void
    {
        Cache::forget(self::productKey($id));
        // Also clear lists (simplified - clear all product lists)
        self::clearProductCache();
    }

    /**
     * Clear specific category cache.
     * 
     * Clears both the specific category cache and all category list caches.
     *
     * @param int $id Category ID
     * @return void
     */
    public static function clearCategory(int $id): void
    {
        Cache::forget(self::categoryKey($id));
        // Also clear lists (simplified - clear all category lists)
        self::clearCategoryCache();
    }

    /**
     * Clear specific customer cache.
     * 
     * Clears both the specific customer cache and all customer list caches.
     *
     * @param int $id Customer ID
     * @return void
     */
    public static function clearCustomer(int $id): void
    {
        Cache::forget(self::customerKey($id));
        // Also clear lists (simplified - clear all customer lists)
        self::clearCustomerCache();
    }
}
