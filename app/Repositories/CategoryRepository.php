<?php

namespace App\Repositories;

use App\Models\Category;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Category Repository
 * 
 * Handles all database operations for categories.
 */
class CategoryRepository
{
    /**
     * Get all categories with optional filters and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection
     */
    public function all(array $filters = [], array $relations = []): Collection
    {
        $cacheKey = CacheService::categoryListKey($filters);
        
        return Cache::remember($cacheKey, CacheService::TTL_LONG, function () use ($filters, $relations) {
            return $this->query($filters, $relations)->get();
        });
    }

    /**
     * Get paginated categories with optional filters and relations.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        $categories = $this->query($filters, $relations)->paginate($perPage);

        // Add products count
        $categories->getCollection()->transform(function ($category) {
            $category->products_count = $category->products()->count();
            return $category;
        });

        return $categories;
    }

    /**
     * Build query with common logic for filtering, sorting, and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function query(array $filters = [], array $relations = [])
    {
        // Handle legacy 'active' filter
        if (isset($filters['active']) && !isset($filters['status'])) {
            $filters['status'] = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN) ? 'active' : 'inactive';
        }

        return Category::query()
            ->filter($filters) // Use scope!
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->ordered(); // Use scope!
    }


    /**
     * Search categories by query string.
     *
     * @param string $query
     * @param array $relations
     * @return Collection
     */
    public function search(string $query, array $relations = []): Collection
    {
        return Category::search($query) // Use scope!
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->withProductCount() // Use scope!
            ->ordered()
            ->get();
    }

    /**
     * Get root categories (categories without parent).
     *
     * @param array $relations
     * @return Collection
     */
    public function getRootCategories(array $relations = []): Collection
    {
        return Category::active() // Use scope!
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->ordered()
            ->get();
    }

    /**
     * Get active categories.
     *
     * @param array $relations
     * @return Collection
     */
    public function getActiveCategories(array $relations = []): Collection
    {
        $cacheKey = CacheService::categoryListKey(['status' => 'active']);
        
        return Cache::remember($cacheKey, CacheService::TTL_DAY, function () use ($relations) {
            return Category::active() // Use scope!
                ->when(!empty($relations), fn($q) => $q->with($relations))
                ->ordered()
                ->get();
        });
    }

    /**
     * Reorder categories (no-op since display_order is removed).
     *
     * @param array $items Array of ['id' => int, 'display_order' => int]
     * @return void
     */
    public function reorder(array $items): void
    {
        // Display order is no longer supported in the schema
        // This method is kept for API compatibility but does nothing
    }

    /**
     * Check if category has products.
     *
     * @param Category $category
     * @return bool
     */
    public function hasProducts(Category $category): bool
    {
        return $category->products()->count() > 0;
    }
}

