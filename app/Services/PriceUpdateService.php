<?php

namespace App\Services;

use App\Repositories\PriceUpdateRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Price Update Service
 * 
 * Handles all business logic for price update operations.
 * 
 * Responsibilities:
 * - Business logic orchestration
 * - Transaction management
 * - Bulk price updates with locking
 * - Price history tracking
 * - Old/new value comparisons
 * - Discount calculations
 * - Partial failure handling
 */
class PriceUpdateService extends BaseService
{
    public function __construct(
        private PriceUpdateRepository $repository,
        private ProductRepository $productRepository
    ) {}

    /**
     * Bulk update product prices.
     * 
     * Handles:
     * - Transaction locking to prevent race conditions
     * - Product updates (via ProductRepository)
     * - Change detection (old vs new values)
     * - Discount calculations
     * - Price update record creation
     * - Partial failure handling (continues on individual errors)
     *
     * @param array $updates Array of update data: ['product_id' => int, 'regular_price' => float?, 'stock_quantity' => float?, 'discount_type' => string?, 'discount_value' => float?]
     * @param int $userId
     * @return array Result array with success status, updated count, errors, and results
     * @throws \Exception
     */
    public function bulkUpdatePrices(array $updates, int $userId): array
    {
        $results = [];
        $errors = [];

        if (empty($updates)) {
            return [
                'success' => true,
                'updated' => 0,
                'errors' => [],
                'results' => [],
            ];
        }

        return $this->transaction(function () use ($updates, $userId, &$results, &$errors) {
            foreach ($updates as $index => $update) {
                try {
                    // Validate required fields
                    if (!isset($update['product_id'])) {
                        $errors[] = [
                            'index' => $index,
                            'error' => 'Product ID is required',
                        ];
                        continue;
                    }

                    $productId = (int) $update['product_id'];

                    // Get product with lock to prevent race conditions (business logic - prevent race conditions)
                    $product = $this->productRepository->findOrFailWithLock($productId);

                    // Get old values before any updates (business logic - change detection)
                    $oldValues = $this->getOldProductValues($product);

                    // Prepare update data (business logic - determine what to update)
                    $updateData = $this->prepareProductUpdateData($update, $oldValues, $product);

                    // Check if there are actual changes (business logic - change detection)
                    $hasPriceChange = $this->hasPriceChange($oldValues, $updateData);
                    $hasStockChange = $this->hasStockChange($oldValues, $updateData);
                    $hasChanges = $hasPriceChange || $hasStockChange;
                    $hasDiscountChange = false;

                    // Update product if there are changes (business logic - orchestration)
                    if ($hasChanges) {
                        $product = $this->productRepository->update($product, array_merge($updateData, [
                            'updated_by' => $userId,
                        ]));

                        // Reload product to get updated values (including any discount changes)
                        $product->refresh();

                        // Get new values after update
                        $newValues = $this->getNewProductValues($product);

                        // Check if discount changed (by comparing old vs new values)
                        $hasDiscountChange = $this->hasDiscountValueChange($oldValues, $newValues);

                        // Calculate new selling price (business logic - price calculation)
                        $newSellingPrice = $this->calculateSellingPrice(
                            $newValues['regular_price'],
                            $newValues['discount_type'],
                            $newValues['discount_value']
                        );

                        // Create price update record if there are any changes (business logic - history tracking)
                        // Include discount changes even if they weren't part of the update
                        if ($hasPriceChange || $hasStockChange || $hasDiscountChange) {
                            $this->createPriceUpdateRecord($product, $oldValues, $newValues, $newSellingPrice, $userId);
                        }

                        $hasChanges = $hasPriceChange || $hasStockChange || $hasDiscountChange;
                    } else {
                        // Even if no direct updates, check if discount changed (external discount updates)
                        $product->refresh();
                        $newValues = $this->getNewProductValues($product);
                        $hasDiscountChange = $this->hasDiscountValueChange($oldValues, $newValues);

                        if ($hasDiscountChange) {
                            // Discount changed externally, create price update record
                            $newSellingPrice = $this->calculateSellingPrice(
                                $newValues['regular_price'],
                                $newValues['discount_type'],
                                $newValues['discount_value']
                            );
                            $this->createPriceUpdateRecord($product, $oldValues, $newValues, $newSellingPrice, $userId);
                            $hasChanges = true;
                        }
                    }

                    // Build result for this product
                    $results[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'updated' => $hasChanges,
                        'changes' => [
                            'price' => $hasPriceChange,
                            'stock' => $hasStockChange,
                            'discount' => $hasDiscountChange,
                        ],
                    ];
                } catch (\Exception $e) {
                    // Log individual product update error but continue with others
                    // This is business logic - partial failure handling
                    Log::warning('Failed to update product in bulk update', [
                        'product_id' => $update['product_id'] ?? null,
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $errors[] = [
                        'product_id' => $update['product_id'] ?? null,
                        'index' => $index,
                        'error' => 'Failed to update product: ' . $e->getMessage(),
                    ];
                }
            }

            return [
                'success' => true,
                'updated' => count($results),
                'errors' => $errors,
                'results' => $results,
            ];
        }, 'Bulk price update failed');
    }

    /**
     * Get price update history for a product.
     * 
     * Handles:
     * - Price history retrieval (via repository)
     * - Relation eager loading
     *
     * @param int $productId
     * @param int $limit
     * @return Collection<int, PriceUpdate>
     */
    public function getProductPriceHistory(int $productId, int $limit = 50): Collection
    {
        $relations = ['updater'];
        return $this->repository->getProductHistory($productId, $limit, $relations);
    }

    /**
     * Get price updates by date range.
     * 
     * Handles:
     * - Price updates retrieval (via repository)
     * - Relation eager loading
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection<int, PriceUpdate>
     */
    public function getPriceUpdatesByDateRange(string $startDate, string $endDate): Collection
    {
        $relations = ['product', 'updater'];
        return $this->repository->getByDateRange($startDate, $endDate, $relations);
    }

    /**
     * Get recent price updates.
     * 
     * Handles:
     * - Recent price updates retrieval (via repository)
     * - Relation eager loading
     *
     * @param int $limit
     * @return Collection<int, PriceUpdate>
     */
    public function getRecent(int $limit = 20): Collection
    {
        $relations = ['product', 'updater'];
        return $this->repository->getRecent($limit, $relations);
    }

    /**
     * Get products for price updates.
     * 
     * Handles:
     * - Product retrieval (via ProductRepository)
     * - Filtering by category and product type
     * - Relation eager loading
     *
     * @param array $filters Filters (search, category_id, product_type)
     * @return Collection<int, Product>
     */
    public function getProductsForPriceUpdate(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $repositoryFilters = ['status' => 'active'];
        $relations = ['category:id,name'];

        // Apply search filter
        if (isset($filters['search'])) {
            $repositoryFilters['search'] = $filters['search'];
        }

        // Apply category filter (handles array, comma-separated string, or single value)
        if (isset($filters['category_id'])) {
            $categoryIds = $filters['category_id'];
            if (is_array($categoryIds)) {
                $repositoryFilters['category_id'] = $categoryIds;
            } else {
                $repositoryFilters['category_id'] = $categoryIds;
            }
        }

        // Apply product type filter (handles array, comma-separated string, or single value)
        if (isset($filters['product_type'])) {
            $productTypes = $filters['product_type'];
            // For now, repository expects single value, so use first if array
            if (is_array($productTypes) && !empty($productTypes)) {
                $repositoryFilters['product_type'] = $productTypes[0];
            } else {
                $repositoryFilters['product_type'] = $productTypes;
            }
        }

        return $this->productRepository->all($repositoryFilters, $relations)
            ->sortBy('name')
            ->values();
    }


    /**
     * Get old product values for comparison.
     * 
     * Business logic: Captures current state before updates.
     *
     * @param \App\Models\Product $product
     * @return array
     */
    protected function getOldProductValues(\App\Models\Product $product): array
    {
        $activeDiscount = $product->activeDiscount();

        return [
            'regular_price' => $product->regular_price,
            'stock_quantity' => $product->stock_quantity,
            'discount_type' => $activeDiscount ? $activeDiscount->discount_type : null,
            'discount_value' => $activeDiscount ? $activeDiscount->discount_value : null,
        ];
    }

    /**
     * Get new product values after update.
     * 
     * Business logic: Captures state after updates.
     *
     * @param \App\Models\Product $product
     * @return array
     */
    protected function getNewProductValues(\App\Models\Product $product): array
    {
        $activeDiscount = $product->activeDiscount();

        return [
            'regular_price' => $product->regular_price,
            'stock_quantity' => $product->stock_quantity,
            'discount_type' => $activeDiscount ? $activeDiscount->discount_type : null,
            'discount_value' => $activeDiscount ? $activeDiscount->discount_value : null,
        ];
    }

    /**
     * Prepare product update data from bulk update input.
     * 
     * Business logic: Determines what fields to update based on input.
     *
     * @param array $update Update data from request
     * @param array $oldValues Current product values
     * @param \App\Models\Product $product
     * @return array Data ready for ProductRepository::update()
     */
    protected function prepareProductUpdateData(array $update, array $oldValues, \App\Models\Product $product): array
    {
        $updateData = [];

        // Update regular price if provided and different
        if (isset($update['regular_price'])) {
            $newRegularPrice = (float) $update['regular_price'];
            if ($newRegularPrice != $oldValues['regular_price']) {
                $updateData['regular_price'] = $newRegularPrice;
            }
        }

        // Update stock quantity if provided and different
        if (isset($update['stock_quantity'])) {
            $newStockQuantity = (float) $update['stock_quantity'];
            if ($newStockQuantity != $oldValues['stock_quantity']) {
                $updateData['stock_quantity'] = $newStockQuantity;
            }
        }

        // Note: Discount updates are handled via ProductDiscount model
        // This service doesn't directly update discounts, but tracks changes
        // If discount fields are provided, we track them for comparison

        return $updateData;
    }


    /**
     * Check if price has changed.
     *
     * @param array $oldValues
     * @param array $updateData
     * @return bool
     */
    protected function hasPriceChange(array $oldValues, array $updateData): bool
    {
        if (!isset($updateData['regular_price'])) {
            return false;
        }

        return (float) $updateData['regular_price'] != (float) $oldValues['regular_price'];
    }

    /**
     * Check if stock has changed.
     *
     * @param array $oldValues
     * @param array $updateData
     * @return bool
     */
    protected function hasStockChange(array $oldValues, array $updateData): bool
    {
        if (!isset($updateData['stock_quantity'])) {
            return false;
        }

        return (float) $updateData['stock_quantity'] != (float) $oldValues['stock_quantity'];
    }

    /**
     * Check if discount values have changed.
     * 
     * Compares old and new discount values to detect changes.
     * Discounts are managed via ProductDiscount model, but we track changes.
     *
     * @param array $oldValues
     * @param array $newValues
     * @return bool
     */
    protected function hasDiscountValueChange(array $oldValues, array $newValues): bool
    {
        $oldType = $oldValues['discount_type'] ?? null;
        $oldValue = $oldValues['discount_value'] ?? null;
        $newType = $newValues['discount_type'] ?? null;
        $newValue = $newValues['discount_value'] ?? null;

        // Compare discount type
        if ($oldType !== $newType) {
            return true;
        }

        // Compare discount value (handle numeric comparison)
        if ($oldValue !== $newValue) {
            if (is_numeric($oldValue) && is_numeric($newValue)) {
                return (float) $oldValue !== (float) $newValue;
            }
            return true;
        }

        return false;
    }

    /**
     * Create a price update record.
     * 
     * Business logic: Records price changes for audit/history purposes.
     *
     * @param \App\Models\Product $product
     * @param array $oldValues
     * @param array $newValues
     * @param float $newSellingPrice
     * @param int $userId
     * @return void
     */
    protected function createPriceUpdateRecord(
        \App\Models\Product $product,
        array $oldValues,
        array $newValues,
        float $newSellingPrice,
        int $userId
    ): void {
        $this->repository->create([
            'product_id' => $product->id,
            'old_regular_price' => $oldValues['regular_price'],
            'new_regular_price' => $newValues['regular_price'],
            'old_discount_type' => $oldValues['discount_type'],
            'new_discount_type' => $newValues['discount_type'],
            'old_discount_value' => $oldValues['discount_value'],
            'new_discount_value' => $newValues['discount_value'],
            'old_stock_quantity' => $oldValues['stock_quantity'],
            'new_stock_quantity' => $newValues['stock_quantity'],
            'new_selling_price' => $newSellingPrice,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Calculate selling price based on regular price, discount type, and discount value.
     * 
     * Business logic for price calculation:
     * - No discount: selling price = regular price
     * - Percentage discount: selling price = regular price - (regular price * discount / 100)
     * - Fixed discount: selling price = regular price - discount
     * - Minimum price: 0 (cannot be negative)
     *
     * @param float|null $regularPrice
     * @param string|null $discountType 'none', 'percentage', or 'fixed'
     * @param float|null $discountValue
     * @return float
     */
    protected function calculateSellingPrice(?float $regularPrice, ?string $discountType, ?float $discountValue): float
    {
        // Handle null or invalid regular price
        if ($regularPrice === null || $regularPrice <= 0) {
            return 0.0;
        }

        // No discount or invalid discount
        if (!$discountType || $discountType === 'none' || !$discountValue || $discountValue <= 0) {
            return round((float) $regularPrice, 2);
        }

        // Calculate discount amount
        if ($discountType === 'percentage') {
            $discountAmount = $regularPrice * ($discountValue / 100);
        } else { // fixed
            $discountAmount = $discountValue;
        }

        // Calculate selling price (regular price - discount)
        $sellingPrice = $regularPrice - $discountAmount;

        // Ensure price is not negative
        return max(0, round((float) $sellingPrice, 2));
    }
}
