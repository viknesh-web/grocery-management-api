<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PriceUpdate\BulkPriceUpdateRequest;
use App\Http\Resources\PriceUpdate\PriceUpdateResource;
use App\Models\PriceUpdate;
use App\Models\Product;
use App\Services\PriceUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Price Update Controller
 * 
 * Handles product price updates, bulk updates, and price history tracking.
 */
class PriceUpdateController extends Controller
{
    protected PriceUpdateService $priceUpdateService;

    public function __construct(PriceUpdateService $priceUpdateService)
    {
        $this->priceUpdateService = $priceUpdateService;
    }

    /**
     * Get all products for price updates.
     */
    public function getProducts(Request $request): JsonResponse
    {
        $query = Product::enabled();

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Filter by category (supports single ID, comma-separated string, or array of IDs)
        if ($request->has('category_id')) {
            $categoryIds = $request->category_id;
            if (is_string($categoryIds) && strpos($categoryIds, ',') !== false) {
                // Comma-separated string
                $categoryIds = array_filter(array_map('trim', explode(',', $categoryIds)));
                if (!empty($categoryIds)) {
                    $query->whereIn('category_id', $categoryIds);
                }
            } elseif (is_array($categoryIds) && !empty($categoryIds)) {
                $query->whereIn('category_id', $categoryIds);
            } elseif (!empty($categoryIds)) {
                $query->where('category_id', $categoryIds);
            }
        }

        // Filter by product type (supports single type, comma-separated string, or array of types)
        if ($request->has('product_type')) {
            $productTypes = $request->product_type;
            if (is_string($productTypes) && strpos($productTypes, ',') !== false) {
                // Comma-separated string
                $productTypes = array_filter(array_map('trim', explode(',', $productTypes)));
                if (!empty($productTypes)) {
                    $query->whereIn('product_type', $productTypes);
                }
            } elseif (is_array($productTypes) && !empty($productTypes)) {
                $query->whereIn('product_type', $productTypes);
            } elseif (!empty($productTypes)) {
                $query->where('product_type', $productTypes);
            }
        }

        $products = $query->orderBy('name')->with(['category:id,name,slug'])->get();
        $data = $products->map(function ($product) {
                $discountActive = $product->isDiscountActive();
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'item_code' => $product->item_code,
                    'image_url' => $product->image_url,
                    'original_price' => (float) $product->original_price,
                    'discount_type' => $discountActive ? $product->discount_type : 'none',
                    'discount_value' => $discountActive && $product->discount_value
                        ? (float) $product->discount_value
                        : null,
                    'selling_price' => (float) $product->selling_price,
                    'stock_quantity' => (float) $product->stock_quantity,
                    'stock_unit' => $product->stock_unit,
                    'product_type' => $product->product_type,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                    ] : null,
                    // Keep current_* fields for backward compatibility if needed
                    'current_original_price' => (float) $product->original_price,
                    'current_discount_type' => $product->discount_type,
                    'current_discount_value' => $product->discount_value ? (float) $product->discount_value : null,
                    'current_selling_price' => (float) $product->selling_price,
                    'current_stock_quantity' => (float) $product->stock_quantity,
                ];
            });
        
        return response()->json([
            'data' => $data
        ], 200);
    }

    /**
     * Bulk update product prices.
     */
    public function bulkUpdate(BulkPriceUpdateRequest $request): JsonResponse
    {
        $result = $this->priceUpdateService->bulkUpdatePrices(
            $request->validated()['updates'],
            $request->user()->id
        );

        if (!$result['success']) {
            return response()->json([
                'message' => 'Price update failed',
                'error' => $result['error'],
                'data' => $result,
            ], 500);
        }

        return response()->json([
            'message' => "Successfully updated {$result['updated']} product(s)",
            'data' => $result,
        ], 200);
    }

    /**
     * Get price update history for a product.
     */
    public function productHistory(Request $request, Product $product): JsonResponse
    {
        $limit = $request->get('limit', 50);
        $history = $this->priceUpdateService->getProductPriceHistory($product->id, $limit);

        return response()->json([
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'item_code' => $product->item_code,
                ],
                'history' => PriceUpdateResource::collection($history),
            ],
        ], 200);
    }

    /**
     * Get price updates by date range.
     */
    public function byDateRange(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $updates = $this->priceUpdateService->getPriceUpdatesByDateRange(
            $request->start_date,
            $request->end_date
        );

        return response()->json([
            'data' => PriceUpdateResource::collection($updates),
        ], 200);
    }

    /**
     * Get recent price updates.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);
        $updates = PriceUpdate::with(['product:id,name,item_code', 'updater:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => PriceUpdateResource::collection($updates),
        ], 200);
    }
}



