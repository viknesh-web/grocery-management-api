<?php

namespace App\Http\Controllers\API;

use App\Exceptions\BusinessException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard Controller
 * 
 * Handles HTTP requests for dashboard operations.
 * 
 * Responsibilities:
 * - HTTP request/response handling
 * - Service method calls
 * - Response formatting (via ApiResponse helper)
 * - Exception handling
 * 
 * Does NOT contain:
 * - Business logic
 * - Direct model queries
 * - Data aggregation
 */
class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    /**
     * Get dashboard statistics.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $data = $this->dashboardService->getStatistics();
            return ApiResponse::success($data);
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Unable to fetch dashboard statistics. Please try again later.',
                null,
                500
            );
        }
    }
}
