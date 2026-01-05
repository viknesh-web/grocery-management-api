<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer Repository
 * 
 * Handles all database operations for customers.
 * Follows Repository pattern: data access only, no business logic.
 * 
 * Responsibilities:
 * - Query building using model scopes
 * - CRUD operations
 * - Filtering and pagination
 * - Cache key generation (caching delegated to service layer)
 */
class CustomerRepository extends BaseRepository
{
    /**
     * Get the model class name.
     */
    protected function model(): string
    {
        return Customer::class;
    }

    /**
     * Get default sort column for customers.  
     */
    protected function getDefaultSortColumn(): string
    {
        return 'created_at';
    }

    /**
     * Get default sort order for customers.
     */
    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    /**
     * Build query with common logic for filtering, sorting, and relations.
     * 
     * Uses Customer model scopes: filter()
     * 
     * Note: Customer model has ordered() scope for name sorting, but we use
     * flexible sorting via sort_by/sort_order filters for consistency.
     *
     * @param array $filters
     * @param array $relations
     * @return Builder
     */
    protected function buildQuery(array $filters = [], array $relations = []): Builder
    {
        $query = $this->query();

        // Apply filter scope (handles: search, status)
        $query->filter($filters);

        // Eager load relations
        if (!empty($relations)) {
            $query->with($relations);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? $this->getDefaultSortColumn();
        $sortOrder = $filters['sort_order'] ?? $this->getDefaultSortOrder();
        $query->orderBy($sortBy, $sortOrder);

        return $query;
    }

    /**
     * Get all customers with optional filters and relations.
     * 
     * Note: Caching should be handled by the service layer.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection<int, Customer>
     */
    public function all(array $filters = [], array $relations = []): Collection
    {
        return $this->buildQuery($filters, $relations)->get();
    }

    /**
     * Get paginated customers with optional filters and relations.
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
     * Search customers by query string.
     * 
     * Uses Customer model scopeSearch().
     *
     * @param string $query
     * @param array $relations
     * @return Collection<int, Customer>
     */
    public function search(string $query, array $relations = []): Collection
    {
        return $this->query()
            ->search($query)
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get active customers.
     * 
     * Uses Customer model scopeActive().
     * 
     * Note: Caching should be handled by the service layer.
     *
     * @param array $relations
     * @return Collection<int, Customer>
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
     * Get inactive customers.
     * 
     * Uses Customer model scopeInactive().
     *
     * @param array $relations
     * @return Collection<int, Customer>
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
     * Get customers by landmark.
     * 
     * Uses Customer model scopeByLandmark().
     *
     * @param string $landmark
     * @param array $filters Additional filters
     * @param array $relations
     * @return Collection<int, Customer>
     */
    public function getByLandmark(string $landmark, array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->byLandmark($landmark)->get();
    }

    /**
     * Get customers with remarks.
     * 
     * Uses Customer model scopeHasRemarks().
     *
     * @param array $filters Additional filters
     * @param array $relations
     * @return Collection<int, Customer>
     */
    public function getWithRemarks(array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->hasRemarks()->get();
    }

    /**
     * Count customers matching filters.
     *
     * @param array $filters
     * @return int
     */
    public function countByFilters(array $filters = []): int
    {
        return $this->buildQuery($filters)->count();
    }

    /**
     * Get cache key for customer list.
     * 
     * Delegates to CacheService for consistency.
     * Actual caching should be handled by the service layer.
     *
     * @param array $filters
     * @return string
     */
    public function getListCacheKey(array $filters = []): string
    {
        return CacheService::customerListKey($filters);
    }

    /**
     * Get cache key for a single customer.
     * 
     * Delegates to CacheService for consistency.
     * Actual caching should be handled by the service layer.
     *
     * @param int $id
     * @return string
     */
    public function getSingleCacheKey(int $id): string
    {
        return CacheService::customerKey($id);
    }

    public function findByPhone(string $phone): ?Customer
    {
        return Customer::where('whatsapp_number', $phone)->first();
    }
}
