<?php

namespace App\Repositories;

use App\Models\PriceUpdate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Price Update Repository
 * 
 * Handles all database operations for price updates.
 * Follows Repository pattern: data access only, no business logic.
 * 
 * Responsibilities:
 * - Query building using model scopes
 * - CRUD operations
 * - Filtering and pagination
 * - Price history queries
 */
class PriceUpdateRepository extends BaseRepository
{
    /**
     * Get the model class name.
     *
     * @return string
     */
    protected function model(): string
    {
        return PriceUpdate::class;
    }

    /**
     * Get default sort column for price updates.
     *
     * @return string
     */
    protected function getDefaultSortColumn(): string
    {
        return 'created_at';
    }

    /**
     * Get default sort order for price updates.
     *
     * @return string
     */
    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    /**
     * Build query with common logic for filtering, sorting, and relations.
     * 
     * Uses PriceUpdate model scopes if available.
     *
     * @param array $filters
     * @param array $relations
     * @return Builder
     */
    protected function buildQuery(array $filters = [], array $relations = []): Builder
    {
        $query = $this->query();

        // Apply product_id filter if provided
        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        // Apply date range filter if provided
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

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
     * Get all price updates with optional filters and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection<int, PriceUpdate>
     */
    public function all(array $filters = [], array $relations = []): Collection
    {
        return $this->buildQuery($filters, $relations)->get();
    }

    /**
     * Get paginated price updates with optional filters and relations.
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
     * Get price update history for a product.
     * 
     * Uses product_id filter and limits results.
     *
     * @param int $productId
     * @param int $limit
     * @param array $relations
     * @return Collection<int, PriceUpdate>
     */
    public function getProductHistory(int $productId, int $limit = 50, array $relations = []): Collection
    {
        return $this->query()
            ->where('product_id', $productId)
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get price updates by date range.
     * 
     * Uses PriceUpdate model scopeDateRange().
     *
     * @param string $startDate
     * @param string $endDate
     * @param array $relations
     * @return Collection<int, PriceUpdate>
     */
    public function getByDateRange(string $startDate, string $endDate, array $relations = []): Collection
    {
        return $this->query()
            ->dateRange($startDate, $endDate)
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get recent price updates.
     *
     * @param int $limit
     * @param array $relations
     * @return Collection<int, PriceUpdate>
     */
    public function getRecent(int $limit = 20, array $relations = []): Collection
    {
        return $this->query()
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Count price updates since a given date.
     *
     * @param \Carbon\Carbon $since
     * @return int
     */
    public function countSince(\Carbon\Carbon $since): int
    {
        return $this->query()
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * Count price updates for a product.
     *
     * @param int $productId
     * @return int
     */
    public function countByProduct(int $productId): int
    {
        return $this->query()
            ->where('product_id', $productId)
            ->count();
    }
}
