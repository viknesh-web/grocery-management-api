<?php

namespace App\Repositories\Contracts;

use App\Models\PriceUpdate;
use Illuminate\Database\Eloquent\Collection;

/**
 * Price Update Repository Interface
 * 
 * Defines the contract for price update data access operations.
 */
interface PriceUpdateRepositoryInterface
{
    /**
     * Create a new price update record.
     *
     * @param array $data
     * @return PriceUpdate
     */
    public function create(array $data): PriceUpdate;

    /**
     * Get price update history for a product.
     *
     * @param int $productId
     * @param int $limit
     * @return Collection
     */
    public function getProductHistory(int $productId, int $limit = 50): Collection;

    /**
     * Get price updates by date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getByDateRange(string $startDate, string $endDate): Collection;

    /**
     * Get recent price updates.
     *
     * @param int $limit
     * @param array $relations
     * @return Collection
     */
    public function getRecent(int $limit = 20, array $relations = []): Collection;

    /**
     * Count price updates since a given date.
     *
     * @param \Carbon\Carbon $since
     * @return int
     */
    public function countSince(\Carbon\Carbon $since): int;
}




