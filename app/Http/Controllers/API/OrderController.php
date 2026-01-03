<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Order Controller
 * 
 * Manages orders in admin panel.
 */
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Get paginated orders list.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'payment_status' => $request->get('payment_status'),
                'customer_id' => $request->get('customer_id'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc'),
            ];

            $perPage = $request->get('per_page', 15);
            $orders = $this->orderService->getPaginated($filters, $perPage);

            return ApiResponse::paginated($orders);
        } catch (\Exception $e) {
            Log::error('Failed to get orders', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to load orders', null, 500);
        }
    }

    /**
     * Get order details.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->find($id);

            if (!$order) {
                return ApiResponse::notFound('Order not found');
            }

            return ApiResponse::success($order->toArray());
        } catch (\Exception $e) {
            Log::error('Failed to get order', ['error' => $e->getMessage(), 'id' => $id]);
            return ApiResponse::error('Failed to load order', null, 500);
        }
    }

    /**
     * Update order status.
     *
     * @param Request $request
     * @param Order $order
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        try {
            $request->validate([
                'status' => ['required', 'string', 'in:pending,confirmed,processing,completed,cancelled']
            ]);

            $updatedOrder = $this->orderService->updateStatus(
                $order,
                $request->status,
                $request->user()->id
            );

            return ApiResponse::success(
                $updatedOrder->toArray(),
                'Order status updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update order status', [
                'error' => $e->getMessage(),
                'id' => $order->id
            ]);
            return ApiResponse::error('Failed to update status', null, 500);
        }
    }

    /**
     * Update payment status.
     *
     * @param Request $request
     * @param Order $order
     * @return JsonResponse
     */
    public function updatePaymentStatus(Request $request, Order $order): JsonResponse
    {
        try {
            $request->validate([
                'payment_status' => ['required', 'string', 'in:unpaid,paid,refunded']
            ]);

            $order->update([
                'payment_status' => $request->payment_status,
                'updated_by' => $request->user()->id,
            ]);

            return ApiResponse::success(
                $order->fresh()->toArray(),
                'Payment status updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update payment status', [
                'error' => $e->getMessage(),
                'id' => $order->id
            ]);
            return ApiResponse::error('Failed to update payment status', null, 500);
        }
    }

    /**
     * Cancel order.
     *
     * @param Request $request
     * @param Order $order
     * @return JsonResponse
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        try {
            $request->validate([
                'reason' => ['nullable', 'string', 'max:500']
            ]);

            $cancelledOrder = $this->orderService->cancel(
                $order,
                $request->reason,
                $request->user()->id
            );

            return ApiResponse::success(
                $cancelledOrder->toArray(),
                'Order cancelled successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to cancel order', [
                'error' => $e->getMessage(),
                'id' => $order->id
            ]);
            return ApiResponse::error('Failed to cancel order', null, 500);
        }
    }

    /**
     * Delete order.
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function destroy(Order $order): JsonResponse
    {
        try {
            $this->orderService->delete($order);

            return ApiResponse::success(null, 'Order deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete order', [
                'error' => $e->getMessage(),
                'id' => $order->id
            ]);
            return ApiResponse::error('Failed to delete order', null, 500);
        }
    }

    /**
     * Get order statistics.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = app(OrderRepository::class)->getStatistics();

            return ApiResponse::success($stats);
        } catch (\Exception $e) {
            Log::error('Failed to get order statistics', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to load statistics', null, 500);
        }
    }
}

