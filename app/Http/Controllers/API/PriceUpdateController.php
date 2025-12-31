<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\PriceUpdate\BulkPriceUpdateRequest;
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
    public function getProducts(Request $request)
    {
        $query = Product::where('status', 'active');

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

        $products = $query->with(['category:id,name'])->orderBy('name')->get();
        
        return ApiResponse::success($products->map(fn($product) => $product->toArray())->toArray());
    }

    /**
     * Bulk update product prices.
     */
    public function bulkUpdate(BulkPriceUpdateRequest $request)
    {
        $result = $this->priceUpdateService->bulkUpdatePrices(
            $request->validated()['updates'],
            $request->user()->id
        );

        if (!$result['success']) {
            return ApiResponse::error(
                $result['error'] ?? 'Price update failed',
                $result,
                500
            );
        }

        return ApiResponse::success($result, "Successfully updated {$result['updated']} product(s)");
    }

    /**
     * Get price update history for a product.
     */
    public function productHistory(Request $request, Product $product)
    {
        $limit = $request->get('limit', 50);
        $history = $this->priceUpdateService->getProductPriceHistory($product->id, $limit);
        
        // Load relationships if needed
        $history->load('updater');

        return ApiResponse::success([
            'product' => $product->toArray(),
            'history' => $history->map(fn($update) => $update->toArray())->toArray(),
        ]);
    }

    /**
     * Get price updates by date range.
     */
    public function byDateRange(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $updates = $this->priceUpdateService->getPriceUpdatesByDateRange(
            $request->start_date,
            $request->end_date
        );
        
        $updates->load('product', 'updater');

        return ApiResponse::success($updates->map(fn($update) => $update->toArray())->toArray());
    }

    /**
     * Get recent price updates.
     */
    public function recent(Request $request)
    {
        $limit = $request->get('limit', 20);
        $updates = PriceUpdate::with(['product', 'updater'])->orderBy('created_at', 'desc')->limit($limit)->get();

        return ApiResponse::success($updates->map(fn($update) => $update->toArray())->toArray());
    }
}



