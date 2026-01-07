<?php

namespace App\Services;

use App\Repositories\PriceUpdateRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PriceUpdateService extends BaseService
{
    public function __construct(
        private PriceUpdateRepository $repository,
        private ProductRepository $productRepository
    ) {}

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
                    $product = $this->productRepository->findOrFailWithLock($productId);
                    $oldValues = $this->getOldProductValues($product);
                    $updateData = $this->prepareProductUpdateData($update, $oldValues, $product);
                    $hasPriceChange = $this->hasPriceChange($oldValues, $updateData);
                    $hasStockChange = $this->hasStockChange($oldValues, $updateData);
                    $hasChanges = $hasPriceChange || $hasStockChange;
                    $hasDiscountChange = false;

                    // Update product if there are changes (business logic - orchestration)
                    if ($hasChanges) {
                        $product = $this->productRepository->update($product, array_merge($updateData, [
                            'updated_by' => $userId,
                        ]));

                        $product->refresh();

                        $newValues = $this->getNewProductValues($product);
                        $hasDiscountChange = $this->hasDiscountValueChange($oldValues, $newValues);
                        $newSellingPrice = $this->calculateSellingPrice(
                            $newValues['regular_price'],
                            $newValues['discount_type'],
                            $newValues['discount_value']
                        );

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

    public function getProductPriceHistory(int $productId, int $limit = 50): Collection
    {
        $relations = ['updater'];
        return $this->repository->getProductHistory($productId, $limit, $relations);
    }
 
    public function getPriceUpdatesByDateRange(string $startDate, string $endDate): Collection
    {
        $relations = ['product', 'updater'];
        return $this->repository->getByDateRange($startDate, $endDate, $relations);
    }

    public function getRecent(int $limit = 20): Collection
    {
        $relations = ['product', 'updater'];
        return $this->repository->getRecent($limit, $relations);
    }

    public function getProductsForPriceUpdate(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $repositoryFilters = ['status' => 'active'];
        $relations = ['category:id,name'];

        // Apply search filter
        if (isset($filters['search'])) {
            $repositoryFilters['search'] = $filters['search'];
        }

        if (isset($filters['category_id'])) {
            $categoryIds = $filters['category_id'];
            if (is_array($categoryIds)) {
                $repositoryFilters['category_id'] = $categoryIds;
            } else {
                $repositoryFilters['category_id'] = $categoryIds;
            }
        }

        if (isset($filters['product_type'])) {
            $productTypes = $filters['product_type'];
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
  
    protected function prepareProductUpdateData(array $update, array $oldValues, \App\Models\Product $product): array
    {
        $updateData = [];

        if (isset($update['regular_price'])) {
            $newRegularPrice = (float) $update['regular_price'];
            if ($newRegularPrice != $oldValues['regular_price']) {
                $updateData['regular_price'] = $newRegularPrice;
            }
        }

        if (isset($update['stock_quantity'])) {
            $newStockQuantity = (float) $update['stock_quantity'];
            if ($newStockQuantity != $oldValues['stock_quantity']) {
                $updateData['stock_quantity'] = $newStockQuantity;
            }
        }

        return $updateData;
    }
  
    protected function hasPriceChange(array $oldValues, array $updateData): bool
    {
        if (!isset($updateData['regular_price'])) {
            return false;
        }

        return (float) $updateData['regular_price'] != (float) $oldValues['regular_price'];
    }

    protected function hasStockChange(array $oldValues, array $updateData): bool
    {
        if (!isset($updateData['stock_quantity'])) {
            return false;
        }

        return (float) $updateData['stock_quantity'] != (float) $oldValues['stock_quantity'];
    }
 
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

    protected function calculateSellingPrice(?float $regularPrice, ?string $discountType, ?float $discountValue): float
    {
        // Handle null or invalid regular price
        if ($regularPrice === null || $regularPrice <= 0) {
            return 0.0;
        }

        if (!$discountType || $discountType === 'none' || !$discountValue || $discountValue <= 0) {
            return round((float) $regularPrice, 2);
        }

        if ($discountType === 'percentage') {
            $discountAmount = $regularPrice * ($discountValue / 100);
        } else {
            $discountAmount = $discountValue;
        }
        $sellingPrice = $regularPrice - $discountAmount;

        return max(0, round((float) $sellingPrice, 2));
    }
}
