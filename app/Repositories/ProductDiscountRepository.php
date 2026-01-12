<?php

namespace App\Repositories;

use App\Models\ProductDiscount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product Discount Repository
 * 
 * Handles all database operations for product discounts.
 */
class ProductDiscountRepository extends BaseRepository
{
    protected function model(): string
    {
        return ProductDiscount::class;
    }

    protected function getDefaultSortColumn(): string
    {
        return 'created_at';
    }

    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    /**
     * Get active discount for a product.
     */
    public function getActiveDiscountForProduct(int $productId, ?Carbon $date = null): ?ProductDiscount
    {
        $now = $date ?? Carbon::now();
        
        return $this->query()
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->first();
    }

    /**
     * Get all discounts for a product.
     */
    public function getProductDiscounts(int $productId): Collection
    {
        return $this->query()
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create or update discount for product.
     */
    public function upsertForProduct(int $productId, array $data): ProductDiscount
    {
        // Deactivate existing active discounts
        $this->query()
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        // Create new discount
        $data['product_id'] = $productId;
        return $this->create($data);
    }

    /**
     * Deactivate all discounts for a product.
     */
    public function deactivateForProduct(int $productId): int
    {
        return $this->query()
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);
    }

    /**
     * Get products with active discounts.
     */
    public function getProductsWithActiveDiscounts(?Carbon $date = null): Collection
    {
        $now = $date ?? Carbon::now();
        
        return $this->query()
            ->with('product')
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->get();
    }

    /**
     * Delete discounts for a product.
     */
    public function deleteForProduct(int $productId): int
    {
        return $this->query()
            ->where('product_id', $productId)
            ->delete();
    }
}