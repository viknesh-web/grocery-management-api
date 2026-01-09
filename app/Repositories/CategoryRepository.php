<?php

namespace App\Repositories;

use App\Models\Category;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Category Repository
 * 
 * Handles all database operations for categories.
 * Follows Repository pattern: data access only, no business logic.
 * 
 * Responsibilities:
 * - Query building using model scopes
 * - CRUD operations
 * - Filtering and pagination
 * - Cache key generation (caching delegated to service layer)
 * - Hierarchical checking (hasProducts)
 * - Tree structure methods (currently flat list)
 */
class CategoryRepository extends BaseRepository
{
    /**
     * Get the model class name.
     *
     * @return string
     */
    protected function model(): string
    {
        return Category::class;
    }

    /**
     * Get default sort column for categories.
     *
     * @return string
     */
    protected function getDefaultSortColumn(): string
    {
        return 'name';
    }

    /**
     * Get default sort order for categories.
     *
     * @return string
     */
    protected function getDefaultSortOrder(): string
    {
        return 'asc';
    }

    /**
     * Build query with common logic for filtering, sorting, and relations.
     * 
     * Uses Category model scopes: filter(), ordered()
     * 
     * Handles legacy 'active' filter conversion to 'status'.
     *
     * @param array $filters
     * @param array $relations
     * @return Builder
     */
    protected function buildQuery(array $filters = [], array $relations = []): Builder
    {
        $query = $this->query();

        if (isset($filters['active']) && !isset($filters['status'])) {
            $filters['status'] = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN) ? 'active' : 'inactive';
        }

        $query->filter($filters);

        if (!empty($relations)) {
            $query->with($relations);
        }

        if (isset($filters['sort_by']) && !empty($filters['sort_by'])) {
            $sortColumn = $filters['sort_by'];
            $sortOrder = $filters['sort_order'] ?? 'asc';
            $query->orderBy($sortColumn, $sortOrder);
        } else {
            $query->ordered();
        }

        return $query;
    }

    /**
     * Get all categories with optional filters and relations.
     * 
     * Note: Caching should be handled by the service layer.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection<int, Category>
     */
    public function all(array $filters = [], array $relations = []): Collection
    {
        return $this->buildQuery($filters, $relations)->get();
    }

    /**
     * Get paginated categories with optional filters and relations.
     * 
     * Note: Products count is added via model's products_count accessor.
     * If you need eager loading of products count, use withProductCount() scope.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        $query = $this->buildQuery($filters, $relations);
        
        // Add product count via scope for better performance
        $query->withProductCount();
        
        return $query->paginate($perPage);
    }

    /**
     * Search categories by query string.
     * 
     * Uses Category model scopeSearch().
     *
     * @param string $query
     * @param array $relations
     * @return Collection<int, Category>
     */
    public function search(string $query, array $relations = []): Collection
    {
        return $this->query()
            ->search($query)
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->withProductCount()
            ->ordered()
            ->get();
    }

    /**
     * Get root categories (categories without parent).
     * 
     * Note: Currently returns all active categories as flat list.
     * Hierarchical structure is not yet implemented.
     * 
     * Uses Category model scopeActive().
     *
     * @param array $relations
     * @return Collection<int, Category>
     */
    public function getRootCategories(array $relations = []): Collection
    {
        return $this->query()
            ->active()
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->ordered()
            ->get();
    }

    /**
     * Get active categories.
     * 
     * Uses Category model scopeActive().
     * 
     * Note: Caching should be handled by the service layer.
     *
     * @param array $relations
     * @return Collection<int, Category>
     */
    public function getActive(array $relations = []): Collection
    {
        return $this->query()
            ->active()
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->ordered()
            ->get();
    }

    /**
     * Get active categories (alias for getActive for backward compatibility).
     * 
     * @param array $relations
     * @return Collection<int, Category>
     */
    public function getActiveCategories(array $relations = []): Collection
    {
        return $this->getActive($relations);
    }

    /**
     * Get inactive categories.
     * 
     * Uses Category model scopeInactive().
     *
     * @param array $relations
     * @return Collection<int, Category>
     */
    public function getInactive(array $relations = []): Collection
    {
        return $this->query()
            ->inactive()
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->ordered()
            ->get();
    }

    /**
     * Get categories that have products.
     * 
     * Uses Category model scopeHasProducts().
     *
     * @param array $filters Additional filters
     * @param array $relations
     * @return Collection<int, Category>
     */
    public function getWithProducts(array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->hasProducts()->get();
    }

    /**
     * Get categories with no products (empty categories).
     * 
     * Uses Category model scopeEmpty().
     *
     * @param array $filters Additional filters
     * @param array $relations
     * @return Collection<int, Category>
     */
    public function getEmpty(array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->empty()->get();
    }

    /**
     * Check if category has products.
     * 
     * Uses Category model's products relationship.
     *
     * @param Category $category
     * @return bool
     */
    public function hasProducts(Category $category): bool
    {
        return $category->products()->exists();
    }

    /**
     * Reorder categories (no-op since display_order is removed).
     * 
     * This method is kept for API compatibility but does nothing.
     * Display order is no longer supported in the schema.
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
     * Count categories matching filters.
     *
     * @param array $filters
     * @return int
     */
    public function countByFilters(array $filters = []): int
    {
        return $this->buildQuery($filters)->count();
    }

    public function getNameAndProductCount(array $filters = [])
    {
        return $this->buildQuery($filters)->withCount('products')->select(['id', 'name', 'image as image_url'])->get();
    }

    /**
     * Get cache key for category list.
     * 
     * Delegates to CacheService for consistency.
     * Actual caching should be handled by the service layer.
     *
     * @param array $filters
     * @return string
     */
    public function getListCacheKey(array $filters = []): string
    {
        return CacheService::categoryListKey($filters);
    }

    /**
     * Get cache key for a single category.
     * 
     * Delegates to CacheService for consistency.
     * Actual caching should be handled by the service layer.
     *
     * @param int $id
     * @return string
     */
    public function getSingleCacheKey(int $id): string
    {
        return CacheService::categoryKey($id);
    }
}
