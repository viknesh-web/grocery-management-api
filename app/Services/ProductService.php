<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\PriceUpdateRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Product Service
 * 
 * Handles all business logic for product operations.
 * 
 * Responsibilities:
 * - Business logic orchestration
 * - Transaction management
 * - Image handling (delegated to ImageService)
 * - Cache management (delegated to CacheService)
 * - Price calculation logic
 * - PriceUpdate record creation
 */
class ProductService extends BaseService
{
    public function __construct(
        private ProductRepository $repository,
        private PriceUpdateRepository $priceUpdateRepository,
        private ImageService $imageService
    ) {}

    /**
     * Get paginated products with filters.
     * 
     * Handles:
     * - Product pagination (via repository)
     * - Filter metadata calculation (via repository)
     * - Relation eager loading
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = ['creator:id,name,email', 'updater:id,name,email', 'category:id,name'];
        
        // Get paginated products via repository
        $products = $this->repository->paginate($filters, $perPage, $relations);

        // Get filter metadata using repository's count method
        // This ensures we use the same filter logic as the pagination
        $totalFiltered = $this->repository->countByFilters($filters);
        $filtersApplied = array_filter($filters, fn($value) => !empty($value));

        // Store metadata as dynamic properties for controller to access
        $products->filters_applied = $filtersApplied;
        $products->total_filtered = $totalFiltered;

        return $products;
    }

    /**
     * Get a product by ID.
     * 
     * Handles:
     * - Product retrieval (via repository)
     * - Relation eager loading
     * - Error handling (returns null if not found)
     *
     * @param int $id
     * @return Product|null
     */
    public function find(int $id): ?Product
    {
        return $this->handle(function () use ($id) {
            $relations = [
                'creator:id,name,email',
                'updater:id,name,email',
                'category:id,name'
            ];
            
            return $this->repository->find($id, $relations);
        }, "Failed to find product with ID: {$id}");
    }

    /**
     * Get a product by ID or throw an exception.
     * 
     * Handles:
     * - Product retrieval (via repository)
     * - Relation eager loading
     * - Throws exception if not found
     *
     * @param int $id
     * @return Product
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id): Product
    {
        return $this->handle(function () use ($id) {
            $relations = [
                'creator:id,name,email',
                'updater:id,name,email',
                'category:id,name'
            ];
            
            return $this->repository->findOrFail($id, $relations);
        }, "Failed to find product with ID: {$id}");
    }

    /**
     * Create a new product.
     * 
     * Handles:
     * - Image upload (delegated to ImageService)
     * - Data preparation (discount logic, user tracking)
     * - Product creation (via repository)
     * - Price update record creation
     * - Cache clearing
     *
     * @param array $data
     * @param UploadedFile|null $image
     * @param int $userId
     * @return Product
     * @throws \Exception
     */
    public function create(array $data, ?UploadedFile $image, int $userId): Product
    {
        return $this->transaction(function () use ($data, $image, $userId) {
            // Handle image upload (business logic - when to upload)
            if ($image) {
                $data['image'] = $this->imageService->uploadProductImage($image);
            }

            // Prepare data (discount logic, user tracking)
            $data = $this->prepareProductData($data, $userId);

            // Create product via repository
            $product = $this->repository->create($data);

            // Create price update record for new product
            $this->createPriceUpdateRecord($product, null, $data, $userId);

            // Post-creation actions (cache clearing)
            $this->afterCreate($product, $data);

            return $product;
        }, 'Failed to create product');
    }

    /**
     * Update a product.
     * 
     * Handles:
     * - Image upload/removal (delegated to ImageService)
     * - Data preparation (discount logic, user tracking)
     * - Change detection (price-related fields)
     * - Product update (via repository)
     * - Price update record creation (if price changed)
     * - Cache clearing
     *
     * @param Product $product
     * @param array $data
     * @param UploadedFile|null $image
     * @param bool $imageRemoved
     * @param int $userId
     * @return Product
     * @throws \Exception
     */
    public function update(Product $product, array $data, ?UploadedFile $image, bool $imageRemoved, int $userId): Product
    {
        return $this->transaction(function () use ($product, $data, $image, $imageRemoved, $userId) {
            // Store old values for comparison
            $oldValues = $this->getOldProductValues($product);

            // Handle image upload/removal (business logic)
            $data = $this->handleImageUpdate($product, $data, $image, $imageRemoved);

            // Prepare data (discount logic, user tracking)
            $data = $this->prepareProductData($data, $userId, $product);

            // Check if price-related fields changed
            $priceChanged = $this->hasPriceChanged($oldValues, $data, $product);

            // Update product via repository
            $product = $this->repository->update($product, $data);

            // Create price update record if price changed
            if ($priceChanged) {
                $finalNewData = $this->getFinalNewValues($oldValues, $data);
                $this->createPriceUpdateRecord($product, $oldValues, $finalNewData, $userId);
            }

            // Post-update actions (cache clearing)
            $this->afterUpdate($product, $data, $oldValues);

            return $product;
        }, 'Failed to update product');
    }

    /**
     * Delete a product.
     * 
     * Handles:
     * - Image cleanup (delegated to ImageService)
     * - Product deletion (via repository)
     * - Cache clearing
     *
     * @param Product $product
     * @return bool
     * @throws \Exception
     */
    public function delete(Product $product): bool
    {
        return $this->transaction(function () use ($product) {
            // Delete image if exists (business logic - cleanup)
            if ($product->image) {
                $this->imageService->deleteProductImage($product->image);
            }

            // Delete product via repository
            $result = $this->repository->delete($product);

            // Post-deletion actions (cache clearing)
            $this->afterDelete($product);

            return $result;
        }, 'Failed to delete product');
    }

    /**
     * Toggle product enabled status.
     * 
     * Handles:
     * - Status calculation (business logic)
     * - Product update (via repository)
     * - User tracking
     * - Cache clearing
     *
     * @param Product $product
     * @param int $userId
     * @return Product
     * @throws \Exception
     */
    public function toggleStatus(Product $product, int $userId): Product
    {
        return $this->transaction(function () use ($product, $userId) {
            // Calculate new status (business logic)
            $newStatus = $product->status === 'active' ? 'inactive' : 'active';
            
            // Update product via repository
            $product = $this->repository->update($product, [
                'status' => $newStatus,
                'updated_by' => $userId,
            ]);

            // Clear cache after status change
            $this->clearModelCache($product);

            return $product;
        }, 'Failed to toggle product status');
    }

    /**
     * Prepare product data for create/update.
     * 
     * Handles:
     * - Discount value clearing when discount_type is 'none'
     * - User tracking (created_by, updated_by)
     *
     * @param array $data
     * @param int $userId
     * @param Product|null $product Existing product (for updates)
     * @return array
     */
    protected function prepareProductData(array $data, int $userId, ?Product $product = null): array
    {
        // Clear discount_value if discount_type is 'none'
        $discountType = $data['discount_type'] ?? $product?->discount_type ?? 'none';
        if ($discountType === 'none') {
            $data['discount_value'] = null;
        }

        // Add user tracking
        if ($product === null) {
            // Creating new product
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
        } else {
            // Updating existing product
            $data['updated_by'] = $userId;
        }

        return $data;
    }

    /**
     * Handle image update logic.
     * 
     * Determines when to upload new image, remove existing image, or keep current.
     *
     * @param Product $product
     * @param array $data
     * @param UploadedFile|null $image
     * @param bool $imageRemoved
     * @return array Modified data array
     */
    protected function handleImageUpdate(Product $product, array $data, ?UploadedFile $image, bool $imageRemoved): array
    {
        if ($image) {
            // Upload new image and delete old one
            if ($product->image) {
                $this->imageService->deleteProductImage($product->image);
            }
            $data['image'] = $this->imageService->uploadProductImage($image, $product->image);
        } elseif ($imageRemoved) {
            // Remove existing image
            if ($product->image) {
                $this->imageService->deleteProductImage($product->image);
            }
            $data['image'] = null;
        }

        return $data;
    }

    /**
     * Get old product values for comparison.
     *
     * @param Product $product
     * @return array
     */
    protected function getOldProductValues(Product $product): array
    {
        return [
            'regular_price' => $product->regular_price,
            'discount_type' => $product->discount_type,
            'discount_value' => $product->discount_value,
            'stock_quantity' => $product->stock_quantity,
        ];
    }

    /**
     * Check if price-related fields have changed.
     *
     * @param array $oldValues
     * @param array $newData
     * @param Product $product
     * @return bool
     */
    protected function hasPriceChanged(array $oldValues, array $newData, Product $product): bool
    {
        $priceFields = ['regular_price', 'discount_type', 'discount_value', 'stock_quantity'];

        foreach ($priceFields as $field) {
            $oldValue = $oldValues[$field] ?? null;
            $newValue = $newData[$field] ?? $product->getAttribute($field);

            // Handle numeric comparison
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

    /**
     * Get final new values for price update record.
     * 
     * Combines new data with old values for fields that weren't changed.
     *
     * @param array $oldValues
     * @param array $newData
     * @return array
     */
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

    /**
     * Create a price update record.
     * 
     * Records changes to price-related fields for audit/history purposes.
     *
     * @param Product $product
     * @param array|null $oldValues Old values from product (null for create)
     * @param array $newData New values being set
     * @param int $userId
     * @return void
     */
    protected function createPriceUpdateRecord(Product $product, ?array $oldValues, array $newData, int $userId): void
    {
        // Determine old values
        $oldRegularPrice = $oldValues['regular_price'] ?? null;
        $oldDiscountType = $oldValues['discount_type'] ?? null;
        $oldDiscountValue = $oldValues['discount_value'] ?? null;
        $oldStockQuantity = $oldValues['stock_quantity'] ?? null;

        // Determine new values
        $newRegularPrice = $newData['regular_price'] ?? null;
        $newDiscountType = $newData['discount_type'] ?? 'none';
        $newDiscountValue = $newData['discount_value'] ?? null;
        $newStockQuantity = $newData['stock_quantity'] ?? null;

        // Calculate new selling price
        $newSellingPrice = $this->calculateSellingPrice($newRegularPrice, $newDiscountType, $newDiscountValue);

        // Create price update record via repository
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
     * @param string $discountType 'none', 'percentage', or 'fixed'
     * @param float|null $discountValue
     * @return float
     */
    protected function calculateSellingPrice(?float $regularPrice, string $discountType, ?float $discountValue): float
    {
        // Handle null or invalid regular price
        if ($regularPrice === null || $regularPrice <= 0) {
            return 0.0;
        }

        // No discount or invalid discount
        if ($discountType === 'none' || !$discountValue || $discountValue <= 0) {
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

    /**
     * Clear cache for a product.
     * 
     * Override from BaseService to implement product-specific cache clearing.
     *
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @param int|null $id
     * @return void
     */
    protected function clearModelCache(?Model $model = null, ?int $id = null): void
    {
        if ($model instanceof Product) {
            CacheService::clearProduct($model->id);
        } elseif ($id !== null) {
            CacheService::clearProduct($id);
        }
    }

    /**
     * Clear all product caches.
     * 
     * Override from BaseService to implement product-specific cache clearing.
     *
     * @return void
     */
    protected function clearAllModelCache(): void
    {
        CacheService::clearProductCache();
    }

    /**
     * Perform actions after product creation.
     * 
     * Override from BaseService to handle post-creation logic.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $data
     * @return void
     */
    protected function afterCreate(Model $model, array $data): void
    {
        // Clear all product caches after creation
        $this->clearAllModelCache();
    }

    /**
     * Perform actions after product update.
     * 
     * Override from BaseService to handle post-update logic.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $data
     * @param array $oldData
     * @return void
     */
    protected function afterUpdate(Model $model, array $data, array $oldData): void
    {
        // Clear specific product cache after update
        if ($model instanceof Product) {
            $this->clearModelCache($model);
        }
    }

    /**
     * Perform actions after product deletion.
     * 
     * Override from BaseService to handle post-deletion logic.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function afterDelete(Model $model): void
    {
        // Clear product cache after deletion
        if ($model instanceof Product) {
            $this->clearModelCache($model);
        }
    }
}
