<?php

namespace App\Http\Controllers\API;

use App\Exceptions\BusinessException;
use App\Exceptions\ValidationException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\PriceUpdate\BulkPriceUpdateRequest;
use App\Http\Requests\PriceUpdate\DateRangeRequest;
use App\Http\Requests\PriceUpdate\GetProductsRequest;
use App\Http\Requests\PriceUpdate\PriceHistoryRequest;
use App\Http\Requests\PriceUpdate\RecentRequest;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\PriceUpdateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * Price Update Controller
 * 
 * Handles HTTP requests for price update operations.
 * 
 * Responsibilities:
 * - HTTP request/response handling
 * - Input validation (via FormRequest classes)
 * - Service method calls
 * - Response formatting (via ApiResponse helper)
 * - Exception handling
 * 
 * Does NOT contain:
 * - Business logic
 * - Direct model queries
 * - Transaction management
 */
class PriceUpdateController extends Controller
{
    public function __construct(
        private PriceUpdateService $priceUpdateService
    ) {}

    /**
     * Get all products for price updates.
     *
     * @param GetProductsRequest $request
     * @return JsonResponse
     */
    public function getProducts(GetProductsRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $products = $this->priceUpdateService->getProductsForPriceUpdate($filters);

            return ApiResponse::success($products->map(fn($product) => $product->toArray())->toArray());
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Unable to fetch products. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Bulk update product prices.
     *
     * @param BulkPriceUpdateRequest $request
     * @return JsonResponse
     */
    public function bulkUpdate(BulkPriceUpdateRequest $request): JsonResponse
    {
        try {
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
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getErrors(), $e->getMessage());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Price update failed. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Get price update history for a product.
     *
     * @param PriceHistoryRequest $request
     * @param Product $product
     * @return JsonResponse
     */
    public function productHistory(PriceHistoryRequest $request, Product $product): JsonResponse
    {
        try {
            $limit = $request->getLimit();
            $history = $this->priceUpdateService->getProductPriceHistory($product->id, $limit);

            return ApiResponse::success([
                'product' => $product->toArray(),
                'history' => $history->map(fn($update) => $update->toArray())->toArray(),
            ]);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Product not found', null, 404);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Unable to fetch price history. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Get price updates by date range.
     *
     * @param DateRangeRequest $request
     * @return JsonResponse
     */
    public function byDateRange(DateRangeRequest $request): JsonResponse
    {
        try {
            $updates = $this->priceUpdateService->getPriceUpdatesByDateRange(
                $request->start_date,
                $request->end_date
            );

            return ApiResponse::success($updates->map(fn($update) => $update->toArray())->toArray());
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getErrors(), $e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Unable to fetch price updates. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Get recent price updates.
     *
     * @param RecentRequest $request
     * @return JsonResponse
     */
    public function recent(RecentRequest $request): JsonResponse
    {
        try {
            $limit = $request->getLimit();
            $updates = $this->priceUpdateService->getRecent($limit);

            return ApiResponse::success($updates->map(fn($update) => $update->toArray())->toArray());
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Unable to fetch recent price updates. Please try again later.',
                null,
                500
            );
        }
    }
}
