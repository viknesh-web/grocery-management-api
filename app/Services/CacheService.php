<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Cache Service
 * 
 * Centralized cache management with consistent key naming and TTLs
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

    /**
     * Get cache key for product list
     */
    public static function productListKey(array $filters): string
    {
        $filterHash = md5(json_encode($filters));
        return self::PREFIX_PRODUCT . ":list:{$filterHash}";
    }

    /**
     * Get cache key for single product
     */
    public static function productKey(int $id): string
    {
        return self::PREFIX_PRODUCT . ":{$id}";
    }

    /**
     * Get cache key for category list
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
     * Get cache key for single category
     */
    public static function categoryKey(int $id): string
    {
        return self::PREFIX_CATEGORY . ":{$id}";
    }

    /**
     * Get cache key for price list PDF
     */
    public static function priceListKey(array $productIds = [], string $layout = 'regular'): string
    {
        $idsHash = md5(json_encode($productIds));
        return self::PREFIX_PRICE_LIST . ":{$layout}:{$idsHash}";
    }

    /**
     * Clear all product caches
     */
    public static function clearProductCache(): void
    {
        // Try to use tags if supported (Redis, Memcached)
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags([self::PREFIX_PRODUCT])->flush();
        } else {
            // Fallback: clear by pattern (file cache doesn't support tags)
            Cache::flush(); // For file cache, we'll clear all (or implement pattern matching)
        }
    }

    /**
     * Clear all category caches
     */
    public static function clearCategoryCache(): void
    {
        // Try to use tags if supported (Redis, Memcached)
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags([self::PREFIX_CATEGORY])->flush();
        } else {
            // Fallback: clear by pattern
            Cache::flush(); // For file cache, we'll clear all (or implement pattern matching)
        }
    }

    /**
     * Clear specific product cache
     */
    public static function clearProduct(int $id): void
    {
        Cache::forget(self::productKey($id));
        // Also clear lists (simplified - clear all product lists)
        self::clearProductCache();
    }

    /**
     * Clear specific category cache
     */
    public static function clearCategory(int $id): void
    {
        Cache::forget(self::categoryKey($id));
        // Also clear lists (simplified - clear all category lists)
        self::clearCategoryCache();
    }
}

