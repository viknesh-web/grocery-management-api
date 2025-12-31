<?php

namespace App\Repositories;

use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Product Repository
 * 
 * Handles all database operations for products.
 */
class ProductRepository
{

    /**
     * Get all products with optional filters and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection
     */
    public function all(array $filters = [], array $relations = []): Collection
    {
        $cacheKey = CacheService::productListKey($filters);
        
        return Cache::remember($cacheKey, CacheService::TTL_MEDIUM, function () use ($filters, $relations) {
            return $this->buildQuery($filters, $relations)->get();
        });
    }

    /**
     * Get paginated products with optional filters and relations.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        return $this->buildQuery($filters, $relations)->paginate($perPage);
    }

    /**
     * Build query with common logic for filtering, sorting, and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildQuery(array $filters = [], array $relations = [])
    {
        return Product::query()
            ->filter($filters) // Use model scope!
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->sortBy(
                $filters['sort_by'] ?? 'created_at',
                $filters['sort_order'] ?? 'desc'
            ); // Use model scope!
    }


    /**
     * Search products by query string.
     *
     * @param string $query
     * @param array $relations
     * @return Collection
     */
    public function search(string $query, array $relations = []): Collection
    {
        return Product::search($query) // Use scope!
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }

    /**
     * Get enabled products.
     *
     * @param array $relations
     * @return Collection
     */
    public function getEnabled(array $relations = []): Collection
    {
        $cacheKey = CacheService::productListKey(['status' => 'active']);
        
        return Cache::remember($cacheKey, CacheService::TTL_LONG, function () use ($relations) {
            return Product::active() // Use scope!
                ->when(!empty($relations), fn($q) => $q->with($relations))
                ->get();
        });
    }

    /**
     * Get products by category ID.
     *
     * @param int $categoryId
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function getByCategory(int $categoryId, array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        // Set category_id in filters to use the filter scope
        $filters['category_id'] = $categoryId;

        return Product::query()
            ->filter($filters) // Use scope!
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->sortBy(
                $filters['sort_by'] ?? 'created_at',
                $filters['sort_order'] ?? 'desc'
            ) // Use scope!
            ->paginate($perPage);
    }

}



