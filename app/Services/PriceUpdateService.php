<?php

namespace App\Services;

use App\Models\PriceUpdate;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PriceUpdateService
{
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

        DB::beginTransaction();

        try {
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

                    // Lock product row to prevent race conditions
                    $product = Product::where('id', $productId)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        $errors[] = [
                            'product_id' => $productId,
                            'index' => $index,
                            'error' => 'Product not found',
                        ];
                        continue;
                    }

                    // Store old values before any updates
                    $oldRegularPrice = $product->regular_price;
                    $oldStockQuantity = $product->stock_quantity;

                    // Get old discount values from active discount if exists
                    $oldDiscount = $product->activeDiscount();
                    $oldDiscountType = $oldDiscount ? $oldDiscount->discount_type : null;
                    $oldDiscountValue = $oldDiscount ? $oldDiscount->discount_value : null;

                    // Track if we need to update the product
                    $needsUpdate = false;
                    $hasPriceChange = false;
                    $hasStockChange = false;

                    // Update regular price if provided and different
                    if (isset($update['regular_price'])) {
                        $newRegularPrice = (float) $update['regular_price'];
                        if ($newRegularPrice != $oldRegularPrice) {
                            $product->regular_price = $newRegularPrice;
                            $needsUpdate = true;
                            $hasPriceChange = true;
                        }
                    }

                    // Update stock quantity if provided and different
                    if (isset($update['stock_quantity'])) {
                        $newStockQuantity = (float) $update['stock_quantity'];
                        if ($newStockQuantity != $oldStockQuantity) {
                            $product->stock_quantity = $newStockQuantity;
                            $needsUpdate = true;
                            $hasStockChange = true;
                        }
                    }

                    // Handle discount updates (if discount fields are provided)
                    $hasDiscountChange = false;
                    if (isset($update['discount_type']) || isset($update['discount_value'])) {
                        // Note: This assumes discount_type and discount_value can be updated directly
                        // If discounts are managed via ProductDiscount model, this logic may need adjustment
                        $newDiscountType = $update['discount_type'] ?? $oldDiscountType;
                        $newDiscountValue = isset($update['discount_value']) ? (float) $update['discount_value'] : $oldDiscountValue;

                        if ($newDiscountType != $oldDiscountType || $newDiscountValue != $oldDiscountValue) {
                            $hasDiscountChange = true;
                            // Discount updates would be handled via ProductDiscount model in a real scenario
                            // For now, we'll track the change but not update directly on product
                        }
                    }

                    // Only save if there are actual changes
                    if ($needsUpdate) {
                        $product->updated_by = $userId;
                        $product->save();
                    }

                    // Check if any relevant fields actually changed
                    $hasChanges = $hasPriceChange || $hasStockChange || $hasDiscountChange;

                    // Only create price update log if there are actual changes
                    if ($hasChanges) {
                        // Reload product to get updated values
                        $product->refresh();
                        
                        // Calculate new selling price using Product accessor
                        $newSellingPrice = $product->selling_price;

                        // Get new discount values
                        $newDiscount = $product->activeDiscount();
                        $newDiscountType = $newDiscount ? $newDiscount->discount_type : null;
                        $newDiscountValue = $newDiscount ? $newDiscount->discount_value : null;

                        PriceUpdate::create([
                            'product_id' => $product->id,
                            'old_regular_price' => $oldRegularPrice,
                            'new_regular_price' => $product->regular_price,
                            'old_discount_type' => $oldDiscountType,
                            'new_discount_type' => $newDiscountType,
                            'old_discount_value' => $oldDiscountValue,
                            'new_discount_value' => $newDiscountValue,
                            'old_stock_quantity' => $oldStockQuantity,
                            'new_stock_quantity' => $product->stock_quantity,
                            'new_selling_price' => $newSellingPrice,
                            'updated_by' => $userId,
                        ]);
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
                    // Log individual product update error but continue with others
                    Log::warning('Failed to update product in bulk update', [
                        'product_id' => $update['product_id'] ?? null,
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $errors[] = [
                        'product_id' => $update['product_id'] ?? null,
                        'index' => $index,
                        'error' => 'Failed to update product: ' . ($e->getMessage()),
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'updated' => count($results),
                'errors' => $errors,
                'results' => $results,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Bulk price update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'updates_count' => count($updates),
                'user_id' => $userId,
            ]);

            return [
                'success' => false,
                'error' => 'Bulk price update failed. Please try again or contact support if the issue persists.',
                'updated' => count($results),
                'errors' => $errors,
                'internal_error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    public function getProductPriceHistory(int $productId, int $limit = 50)
    {
        return PriceUpdate::where('product_id', $productId)->with('updater')->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    public function getPriceUpdatesByDateRange(string $startDate, string $endDate)
    {
        return PriceUpdate::dateRange($startDate, $endDate)->with(['product', 'updater'])->orderBy('created_at', 'desc')->get();
    }
}


