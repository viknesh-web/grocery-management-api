<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Repositories\CategoryRepository;
use App\Repositories\PriceUpdateRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Product Service - Updated with Category Status Check
 */
class ProductService extends BaseService
{
    public function __construct(
        private ProductRepository $repository,
        private PriceUpdateRepository $priceUpdateRepository,
        private CategoryRepository $categoryRepository,
        private ImageService $imageService
    ) {}

    public function getPaginated(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = ['category:id,name,status'];
        $products = $this->repository->paginate($filters, $perPage, $relations);
        $totalFiltered = $this->repository->countByFilters($filters);
        $filtersApplied = array_filter($filters, fn($value) => !empty($value));
        $products->filters_applied = $filtersApplied;
        $products->total_filtered = $totalFiltered;

        return $products;
    }

    public function find(int $id): ?Product
    {
        return $this->handle(function () use ($id) {
            $relations = [
                'category:id,name,status'
            ];
            
            return $this->repository->find($id, $relations);
        }, "Failed to find product with ID: {$id}");
    }
 
    public function findOrFail(int $id): Product
    {
        return $this->handle(function () use ($id) {
            $relations = [
                'category:id,name,status'
            ];
            
            return $this->repository->findOrFail($id, $relations);
        }, "Failed to find product with ID: {$id}");
    }
   
    public function create(array $data, ?UploadedFile $image, int $userId): Product
    {
        return $this->transaction(function () use ($data, $image, $userId) {
            // Check if category is active (if category_id is provided)
            if (isset($data['category_id'])) {
                $this->validateCategoryStatus($data['category_id'], $data['status'] ?? 'active');
            }

            if ($image) {
                $data['image'] = $this->imageService->uploadProductImage($image);
            }

            $data = $this->prepareProductData($data, $userId);
            $product = $this->repository->create($data);
            $this->createPriceUpdateRecord($product, null, $data, $userId);
            $this->afterCreate($product, $data);

            return $product;
        }, 'Failed to create product');
    }
  
    public function update(Product $product, array $data, ?UploadedFile $image, bool $imageRemoved, int $userId): Product
    {
        return $this->transaction(function () use ($product, $data, $image, $imageRemoved, $userId) {
            // Check if trying to activate product with inactive category
            if (isset($data['status']) && $data['status'] === 'active') {
                $categoryId = $data['category_id'] ?? $product->category_id;
                $this->validateCategoryStatus($categoryId, 'active');
            }

            $oldValues = $this->getOldProductValues($product);
            $data = $this->handleImageUpdate($product, $data, $image, $imageRemoved);
            $data = $this->prepareProductData($data, $userId, $product);
            $priceChanged = $this->hasPriceChanged($oldValues, $data, $product);
            $product = $this->repository->update($product, $data);
            if ($priceChanged) {
                $finalNewData = $this->getFinalNewValues($oldValues, $data);
                $this->createPriceUpdateRecord($product, $oldValues, $finalNewData, $userId);
            }

            $this->afterUpdate($product, $data, $oldValues);

            return $product;
        }, 'Failed to update product');
    }

    public function delete(Product $product): bool
    {
        return $this->transaction(function () use ($product) {
            if ($product->image) {
                $this->imageService->deleteProductImage($product->image);
            }
            $result = $this->repository->delete($product);
            $this->afterDelete($product);

            return $result;
        }, 'Failed to delete product');
    }

    /**
     * Toggle product status with category validation.
     *
     * @param Product $product
     * @param int $userId
     * @return Product
     * @throws BusinessException if trying to activate product with inactive category
     */
    public function toggleStatus(Product $product, int $userId): Product
    {
        return $this->transaction(function () use ($product, $userId) {       
            $newStatus = $product->status === 'active' ? 'inactive' : 'active';
            
            // If trying to activate, check category status
            if ($newStatus === 'active') {
                $this->validateCategoryStatus($product->category_id, 'active');
            }
            
            $product = $this->repository->update($product, [
                'status' => $newStatus,
                'updated_by' => $userId,
            ]);

            $this->clearModelCache($product);

            return $product;
        }, 'Failed to toggle product status');
    }

    /**
     * Validate category status before activating product.
     *
     * @param int $categoryId
     * @param string $desiredProductStatus
     * @throws BusinessException if category is inactive
     */
    private function validateCategoryStatus(int $categoryId, string $desiredProductStatus): void
    {
        if ($desiredProductStatus !== 'active') {
            return; // No validation needed for inactive products
        }

        $category = $this->categoryRepository->find($categoryId);

        if (!$category) {
            throw new BusinessException('Category not found');
        }

        if ($category->status !== 'active') {
            throw new BusinessException(
                "Cannot activate product. Category '{$category->name}' is inactive. Please activate the category first.",
                [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'category_status' => $category->status,
                ]
            );
        }
    }

    protected function prepareProductData(array $data, int $userId, ?Product $product = null): array
    {
        $discountType = $data['discount_type'] ?? $product?->discount_type ?? 'none';
        if ($discountType === 'none') {
            $data['discount_value'] = null;
        }        

        return $data;
    }

    protected function handleImageUpdate(Product $product, array $data, ?UploadedFile $image, bool $imageRemoved): array
    {
        if ($image) {
            if ($product->image) {
                $this->imageService->deleteProductImage($product->image);
            }
            $data['image'] = $this->imageService->uploadProductImage($image, $product->image);
        } elseif ($imageRemoved) {
            if ($product->image) {
                $this->imageService->deleteProductImage($product->image);
            }
            $data['image'] = null;
        }

        return $data;
    }

    protected function getOldProductValues(Product $product): array
    {
        return [
            'regular_price' => $product->regular_price,
            'discount_type' => $product->discount_type,
            'discount_value' => $product->discount_value,
            'stock_quantity' => $product->stock_quantity,
        ];
    }
 
    protected function hasPriceChanged(array $oldValues, array $newData, Product $product): bool
    {
        $priceFields = ['regular_price', 'discount_type', 'discount_value', 'stock_quantity'];

        foreach ($priceFields as $field) {
            $oldValue = $oldValues[$field] ?? null;
            $newValue = $newData[$field] ?? $product->getAttribute($field);

            if (is_numeric($oldValue) && is_numeric($newValue)) {
                if ((float) $oldValue !== (float) $newValue) {
                    return true;
                }
            } elseif ($oldValue !== $newValue) {
                return true;
            }
        }

        return false;
    }

    protected function getFinalNewValues(array $oldValues, array $newData): array
    {
        return [
            'regular_price' => $newData['regular_price'] ?? $oldValues['regular_price'],
            'discount_type' => $newData['discount_type'] ?? $oldValues['discount_type'],
            'discount_value' => array_key_exists('discount_value', $newData) 
                ? $newData['discount_value'] 
                : $oldValues['discount_value'],
            'stock_quantity' => $newData['stock_quantity'] ?? $oldValues['stock_quantity'],
        ];
    }
 
    protected function createPriceUpdateRecord(Product $product, ?array $oldValues, array $newData, int $userId): void
    {
        $oldRegularPrice = $oldValues['regular_price'] ?? null;
        $oldDiscountType = $oldValues['discount_type'] ?? null;
        $oldDiscountValue = $oldValues['discount_value'] ?? null;
        $oldStockQuantity = $oldValues['stock_quantity'] ?? null;

        $newRegularPrice = $newData['regular_price'] ?? null;
        $newDiscountType = $newData['discount_type'] ?? 'none';
        $newDiscountValue = $newData['discount_value'] ?? null;
        $newStockQuantity = $newData['stock_quantity'] ?? null;

        $newSellingPrice = $this->calculateSellingPrice($newRegularPrice, $newDiscountType, $newDiscountValue);

        $this->priceUpdateRepository->create([
            'product_id' => $product->id,
            'old_regular_price' => $oldRegularPrice,
            'new_regular_price' => $newRegularPrice,
            'old_discount_type' => $oldDiscountType,
            'new_discount_type' => $newDiscountType,
            'old_discount_value' => $oldDiscountValue,
            'new_discount_value' => $newDiscountValue,
            'old_stock_quantity' => $oldStockQuantity,
            'new_stock_quantity' => $newStockQuantity,
            'new_selling_price' => $newSellingPrice,
            'updated_by' => $userId,
        ]);
    }

    protected function calculateSellingPrice(?float $regularPrice, string $discountType, ?float $discountValue): float
    {
        if ($regularPrice === null || $regularPrice <= 0) {
            return 0.0;
        }
        if ($discountType === 'none' || !$discountValue || $discountValue <= 0) {
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

    protected function clearModelCache(?Model $model = null, ?int $id = null): void
    {
        if ($model instanceof Product) {
            CacheService::clearProduct($model->id);
        } elseif ($id !== null) {
            CacheService::clearProduct($id);
        }
    }
   
    protected function clearAllModelCache(): void
    {
        CacheService::clearProductCache();
    }
   
    protected function afterCreate(Model $model, array $data): void
    {
        $this->clearAllModelCache();
    }
   
    protected function afterUpdate(Model $model, array $data, array $oldData): void
    {
        if ($model instanceof Product) {
            $this->clearModelCache($model);
        }
    }
   
    protected function afterDelete(Model $model): void
    {
        if ($model instanceof Product) {
            $this->clearModelCache($model);
        }
    }
}