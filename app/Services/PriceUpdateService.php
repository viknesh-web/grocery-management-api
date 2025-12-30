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

        DB::beginTransaction();

        try {
            foreach ($updates as $update) {
                $product = Product::find($update['product_id']);

                if (!$product) {
                    $errors[] = [
                        'product_id' => $update['product_id'],
                        'error' => 'Product not found',
                    ];
                    continue;
                }

                // Store old values
                $oldRegularPrice = $product->regular_price;
                $oldDiscountType = $product->discount_type;
                $oldDiscountValue = $product->discount_value;
                $oldStockQuantity = $product->stock_quantity;

                // Update product
                $product->regular_price = $update['regular_price'] ?? $product->regular_price;
                $product->discount_type = $update['discount_type'] ?? $product->discount_type;
                $product->discount_value = $update['discount_value'] ?? $product->discount_value;
                $product->stock_quantity = $update['stock_quantity'] ?? $product->stock_quantity;
                $product->updated_by = $userId;
                $product->save();

                // Create price update log only if relevant fields actually changed
                $hasChanges = ($oldRegularPrice != $product->regular_price) ||
                    ($oldDiscountType != $product->discount_type) ||
                    ($oldDiscountValue != $product->discount_value) ||
                    ($oldStockQuantity != $product->stock_quantity);

                if ($hasChanges) {
                    // Calculate new selling price using Product accessor
                    $newSellingPrice = $product->selling_price;

                    PriceUpdate::create([
                        'product_id' => $product->id,
                        'old_regular_price' => $oldRegularPrice,
                        'new_regular_price' => $product->regular_price,
                        'old_discount_type' => $oldDiscountType,
                        'new_discount_type' => $product->discount_type,
                        'old_discount_value' => $oldDiscountValue,
                        'new_discount_value' => $product->discount_value,
                        'old_stock_quantity' => $oldStockQuantity,
                        'new_stock_quantity' => $product->stock_quantity,
                        'new_selling_price' => $newSellingPrice,
                        'updated_by' => $userId,
                    ]);
                }

                $results[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'updated' => true,
                ];
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
                'updates' => $updates,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'updated' => count($results),
                'errors' => $errors,
            ];
        }
    }

    public function getProductPriceHistory(int $productId, int $limit = 50)
    {
        return PriceUpdate::where('product_id', $productId)->with('updater:id,name,email')->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    public function getPriceUpdatesByDateRange(string $startDate, string $endDate)
    {
        return PriceUpdate::dateRange($startDate, $endDate)->with(['product:id,name,item_code', 'updater:id,name,email'])->orderBy('created_at', 'desc')->get();
    }
}


