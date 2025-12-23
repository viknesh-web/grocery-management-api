<?php

namespace App\Repositories;

use App\Models\PriceUpdate;
use App\Repositories\Contracts\PriceUpdateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Price Update Repository
 * 
 * Handles all database operations for price updates.
 */
class PriceUpdateRepository implements PriceUpdateRepositoryInterface
{
    /**
     * Create a new price update record.
     *
     * @param array $data
     * @return PriceUpdate
     */
    public function create(array $data): PriceUpdate
    {
        return PriceUpdate::create($data);
    }

    /**
     * Get price update history for a product.
     *
     * @param int $productId
     * @param int $limit
     * @return Collection
     */
    public function getProductHistory(int $productId, int $limit = 50): Collection
    {
        return PriceUpdate::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get price updates by date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getByDateRange(string $startDate, string $endDate): Collection
    {
        return PriceUpdate::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get recent price updates.
     *
     * @param int $limit
     * @param array $relations
     * @return Collection
     */
    public function getRecent(int $limit = 20, array $relations = []): Collection
    {
        $query = PriceUpdate::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->orderBy('created_at', 'desc')
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
        return PriceUpdate::where('created_at', '>=', $since)->count();
    }
}



