<?php

namespace App\Services;

use App\Repositories\PriceUpdateRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductDiscountRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Price Update Service - Updated with ProductDiscount Integration
 */
class PriceUpdateService extends BaseService
{
    public function __construct(
        private PriceUpdateRepository $repository,
        private ProductRepository $productRepository,
        private ProductDiscountRepository $discountRepository
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
                    $discountData = $this->prepareDiscountUpdateData($update, $oldValues, $product);
                    
                    $hasPriceChange = $this->hasPriceChange($oldValues, $updateData);
                    $hasStockChange = $this->hasStockChange($oldValues, $updateData);
                    $hasDiscountChange = $this->hasDiscountChange($oldValues, $discountData);
                    $hasChanges = $hasPriceChange || $hasStockChange || $hasDiscountChange;

                    // Update product if there are changes
                    if ($hasPriceChange || $hasStockChange) {
                        $product = $this->productRepository->update($product, array_merge($updateData, [
                            'updated_by' => $userId,
                        ]));
                    }
                    
                    // Update discount if changed
                    if ($hasDiscountChange) {
                        if ($discountData && $discountData['discount_type'] !== 'none') {
                            $this->discountRepository->upsertForProduct($productId, $discountData);
                        } else {
                            $this->discountRepository->deactivateForProduct($productId);
                        }
                    }

                    $product->refresh();

                    if ($hasChanges) {
                        $newValues = $this->getNewProductValues($product);
                        $newSellingPrice = $product->selling_price;
                        $this->createPriceUpdateRecord($product, $oldValues, $newValues, $newSellingPrice, $userId);
                    }

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
        $relations = ['category:id,name', 'discounts'];

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
            'discount_type' => $activeDiscount?->discount_type ?? 'none',
            'discount_value' => $activeDiscount?->discount_value,
        ];
    }

    protected function getNewProductValues(\App\Models\Product $product): array
    {
        $activeDiscount = $product->activeDiscount();

        return [
            'regular_price' => $product->regular_price,
            'stock_quantity' => $product->stock_quantity,
            'discount_type' => $activeDiscount?->discount_type ?? 'none',
            'discount_value' => $activeDiscount?->discount_value,
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
    
    protected function prepareDiscountUpdateData(array $update, array $oldValues, \App\Models\Product $product): ?array
    {
        if (!isset($update['discount_type'])) {
            return null;
        }

        $newDiscountType = $update['discount_type'];
        $newDiscountValue = $update['discount_value'] ?? null;

        if ($newDiscountType === 'none') {
            return ['discount_type' => 'none'];
        }

        if ($newDiscountValue === null || $newDiscountValue <= 0) {
            return null;
        }

        return [
            'discount_type' => $newDiscountType,
            'discount_value' => $newDiscountValue,
            'start_date' => $update['discount_start_date'] ?? null,
            'end_date' => $update['discount_end_date'] ?? null,
            'status' => 'active',
        ];
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
 
    protected function hasDiscountChange(array $oldValues, ?array $newDiscountData): bool
    {
        if ($newDiscountData === null) {
            return false;
        }

        $oldType = $oldValues['discount_type'] ?? 'none';
        $oldValue = $oldValues['discount_value'];
        $newType = $newDiscountData['discount_type'] ?? 'none';
        $newValue = $newDiscountData['discount_value'] ?? null;

        if ($oldType !== $newType) {
            return true;
        }

        if ($newType === 'none') {
            return false;
        }

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
}