<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderRequest;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Order Controller
 * 
 * Manages orders in admin panel.
 */
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private OrderRepository $orderRepository
    ) {}

    /**
     * Get paginated orders list.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(OrderRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $pagination = $request->getPagination();
            
            $page = $pagination['page'] + 1;
            $perPage = $pagination['limit'];
            
            \Illuminate\Pagination\Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });
            
            $orders = $this->orderService->getPaginated($filters, $perPage);
            return response()->json([
                'success' => true,
                'data' => $orders->items(),
                'total' => $orders->total(),
                'page' => max(0, $page - 1),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get orders', [
                'error' => $e->getMessage(),
                'filters' => $filters ?? [],
            ]);
            return ApiResponse::error('Failed to load orders', null, 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->find($id);

            if (!$order) {
                return ApiResponse::notFound('Order not found');
            }

            $order->load([
                'customer:id,name,whatsapp_number,email,address',
                'items.product:id,name,item_code,image',
            ]);


            return ApiResponse::success($order->toArray());
        } catch (\Exception $e) {
            Log::error('Failed to get order', ['error' => $e->getMessage(), 'id' => $id]);
            return ApiResponse::error('Failed to load order', null, 500);
        }
    }

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

    public function customerOrders(Request $request, int $customerId): JsonResponse
    {
        try {
            $limit = $request->get('limit', 20);
            
            $orders = $this->orderRepository->getByCustomer(
                $customerId,
                $limit,
                ['items:id,order_id,product_name,quantity,total']
            );

            return ApiResponse::success($orders->toArray());
        } catch (\Exception $e) {
            Log::error('Failed to get customer orders', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId,
            ]);
            return ApiResponse::error('Failed to load customer orders', null, 500);
        }
    }

     public function generateOrderUrl(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
                Log::info( $user);
            if (!$user || !$user->master) {
                return ApiResponse::forbidden('Only admin users can create orders for customers');
            }
            
            $signedUrl = URL::temporarySignedRoute(
                'order.form',
                now()->addHour(),
                [
                    'is_admin' => 1,
                    'admin_user_id' => $user->id,
                ]
            );
            
            return ApiResponse::success([
                'url' => $signedUrl,
                'expires_at' => now()->addHour()->toIso8601String(),
            ]);
            
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate order URL', null, 500);
        }
    }
}

