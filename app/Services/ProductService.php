<?php

namespace App\Services;

use App\Models\PriceUpdate;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\ProductFilterService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Product Service
 * 
 * Handles all business logic for product operations.
 */
class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $repository,
        private ImageService $imageService,
        private ProductFilterService $filterService
    ) {}

    /**
     * Get paginated products with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = ['creator:id,name,email', 'updater:id,name,email', 'category:id,name,slug'];
        $products = $this->repository->paginate($filters, $perPage, $relations);

        // Get filter metadata
        $query = \App\Models\Product::query();
        $query = $this->filterService->applyFilters($query, $filters);
        $metadata = $this->filterService->getFilterMetadata($query, $filters);

        // Store metadata as dynamic properties for controller to access
        $products->filters_applied = $metadata['filters_applied'] ?? [];
        $products->total_filtered = $metadata['total_count'] ?? $products->total();

        return $products;
    }

    /**
     * Get a product by ID.
     *
     * @param int $id
     * @return Product|null
     */
    public function find(int $id): ?Product
    {
        $relations = ['creator:id,name,email', 'updater:id,name,email', 'category:id,name,slug'];
        return $this->repository->find($id, $relations);
    }

    /**
     * Create a new product.
     *
     * @param array $data
     * @param UploadedFile|null $image
     * @param int $userId
     * @return Product
     */
    public function create(array $data, ?UploadedFile $image, int $userId): Product
    {
        DB::beginTransaction();
        try {
            // Handle image upload
            if ($image) {
                $data['image'] = $this->imageService->uploadProductImage($image);
            }

            // Clear discount_value if discount_type is 'none'
            if (($data['discount_type'] ?? 'none') === 'none') {
                $data['discount_value'] = null;
            }
            
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;

            $product = $this->repository->create($data);

            // Create price update record for new product
            $this->createPriceUpdateRecord($product, null, $data, $userId);

            // Handle variations if provided
            if (isset($data['variations']) && is_array($data['variations'])) {
                $this->createVariations($product, $data['variations']);
            }

            DB::commit();
            return $product->load('variations');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a product.
     *
     * @param Product $product
     * @param array $data
     * @param UploadedFile|null $image
     * @param bool $imageRemoved
     * @param int $userId
     * @return Product
     */
    public function update(Product $product, array $data, ?UploadedFile $image, bool $imageRemoved, int $userId): Product
    {
        DB::beginTransaction();
        try {
            // Store old values before update
            $oldValues = [
                'original_price' => $product->original_price,
                'discount_type' => $product->discount_type,
                'discount_value' => $product->discount_value,
                'stock_quantity' => $product->stock_quantity,
            ];

            // Check if price-related fields changed
            $priceChanged = false;

            if (array_key_exists('original_price', $data) && (float) $data['original_price'] !== (float) $product->original_price) {
                $priceChanged = true;
            }

            if (array_key_exists('discount_type', $data) && $data['discount_type'] !== $product->discount_type) {
                $priceChanged = true;
            }

            if (array_key_exists('discount_value', $data) && (float) $data['discount_value'] !== (float) $product->discount_value) {
                $priceChanged = true;
            }

            if (array_key_exists('stock_quantity', $data) && (float) $data['stock_quantity'] !== (float) $product->stock_quantity) {
                $priceChanged = true;
            }

            if ($image) {
                // Delete old image if exists
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

            // Clear discount_value if discount_type is 'none'
            $discountType = $data['discount_type'] ?? $product->discount_type ?? null;
            if ($discountType === 'none') {
                $data['discount_value'] = null;
            }

            $data['updated_by'] = $userId;

            $this->repository->update($product, $data);

            // Refresh product to get updated values
            $product->refresh();

            // Create price update record if price-related fields changed
            if ($priceChanged) {
                // Get final new values (from data or old values if not changed)
                // Note: discount_value is already cleared in $data if discount_type is 'none'
                $finalNewData = [
                    'original_price' => $data['original_price'] ?? $oldValues['original_price'],
                    'discount_type' => $data['discount_type'] ?? $oldValues['discount_type'],
                    'discount_value' => array_key_exists('discount_value', $data) ? $data['discount_value'] : $oldValues['discount_value'],
                    'stock_quantity' => $data['stock_quantity'] ?? $oldValues['stock_quantity'],
                ];
                $this->createPriceUpdateRecord($product, $oldValues, $finalNewData, $userId);
            }

            // Handle variations if provided
            if (isset($data['variations']) && is_array($data['variations'])) {
                // Delete existing variations
                $product->variations()->delete();
                // Create new variations
                $this->createVariations($product, $data['variations']);
            }

            DB::commit();
            return $product->load('variations');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a product.
     *
     * @param Product $product
     * @return bool
     */
    public function delete(Product $product): bool
    {
        DB::beginTransaction();
        try {
            // Delete image if exists
            if ($product->image) {
                $this->imageService->deleteProductImage($product->image);
            }

            $result = $this->repository->delete($product);

            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Toggle product enabled status.
     *
     * @param Product $product
     * @param int $userId
     * @return Product
     */
    public function toggleStatus(Product $product, int $userId): Product
    {
        $this->repository->update($product, [
            'enabled' => !$product->enabled,
            'updated_by' => $userId,
        ]);

        return $product->fresh();
    }

    /**
     * Create variations for a product.
     *
     * @param Product $product
     * @param array $variations
     * @return void
     */
    private function createVariations(Product $product, array $variations): void
    {
        foreach ($variations as $variationData) {
            // Generate SKU if not provided
            if (empty($variationData['sku'])) {
                $variationData['sku'] = $this->generateSku($product->item_code, $variationData['quantity'], $variationData['unit']);
            }

            $product->variations()->create([
                'quantity' => $variationData['quantity'],
                'unit' => $variationData['unit'],
                'price' => $variationData['price'],
                'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                'sku' => $variationData['sku'],
                'enabled' => $variationData['enabled'] ?? true,
            ]);
        }
    }

    /**
     * Generate SKU for a variation.
     *
     * @param string $itemCode
     * @param float $quantity
     * @param string $unit
     * @return string
     */
    private function generateSku(string $itemCode, float $quantity, string $unit): string
    {
        // Format: ITEMCODE-QUANTITYUNIT (e.g., CAR-001-1KG, RICE-500GM)
        $quantityStr = $quantity == (int) $quantity ? (int) $quantity : number_format($quantity, 2, '', '');
        return strtoupper($itemCode . '-' . $quantityStr . $unit);
    }

    /**
     * Create a price update record.
     *
     * @param Product $product
     * @param array|null $oldValues Old values from product (null for create)
     * @param array $newData New values being set
     * @param int $userId
     * @return void
     */
    private function createPriceUpdateRecord(Product $product, ?array $oldValues, array $newData, int $userId): void
    {
        // Determine old values
        $oldOriginalPrice = $oldValues['original_price'] ?? null;
        $oldDiscountType = $oldValues['discount_type'] ?? null;
        $oldDiscountValue = $oldValues['discount_value'] ?? null;
        $oldStockQuantity = $oldValues['stock_quantity'] ?? null;

        // Determine new values (from newData, which should contain the final values)
        $newOriginalPrice = $newData['original_price'] ?? null;
        $newDiscountType = $newData['discount_type'] ?? 'none';
        $newDiscountValue = $newData['discount_value'] ?? null;
        $newStockQuantity = $newData['stock_quantity'] ?? null;

        // Calculate new selling price
        $newSellingPrice = $this->calculateSellingPrice($newOriginalPrice, $newDiscountType, $newDiscountValue);

        PriceUpdate::create([
            'product_id' => $product->id,
            'old_original_price' => $oldOriginalPrice,
            'new_original_price' => $newOriginalPrice,
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
     * Calculate selling price based on original price, discount type, and discount value.
     *
     * @param float $originalPrice
     * @param string $discountType
     * @param float|null $discountValue
     * @return float
     */
    private function calculateSellingPrice(float $originalPrice, string $discountType, ?float $discountValue): float
    {
        if ($discountType === 'none' || !$discountValue || $discountValue <= 0) {
            return round((float) $originalPrice, 2);
        }

        if ($discountType === 'percentage') {
            $discountAmount = $originalPrice * ($discountValue / 100);
            $sellingPrice = $originalPrice - $discountAmount;
        } else { // fixed
            $sellingPrice = $originalPrice - $discountValue;
        }

        return max(0, round((float) $sellingPrice, 2));
    }
}

