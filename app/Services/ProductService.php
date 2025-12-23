<?php

namespace App\Services;

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
            // Handle image upload
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

            // Handle variations if provided
            if (isset($data['variations']) && is_array($data['variations'])) {
                // Delete existing variations
                $product->variations()->delete();
                // Create new variations
                $this->createVariations($product, $data['variations']);
            }

            $product->refresh();

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
}

