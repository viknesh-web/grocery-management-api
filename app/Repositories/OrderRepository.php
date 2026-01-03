<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Order Repository
 * 
 * Handles all database operations for orders.
 * Follows Repository pattern: data access only, no business logic.
 * 
 * Responsibilities:
 * - Query building using model scopes
 * - CRUD operations
 * - Filtering and pagination
 * - Order number generation
 */
class OrderRepository extends BaseRepository
{
    /**
     * Get the model class name.
     *
     * @return string
     */
    protected function model(): string
    {
        return Order::class;
    }

    /**
     * Get default sort column for orders.
     *
     * @return string
     */
    protected function getDefaultSortColumn(): string
    {
        return 'created_at';
    }

    /**
     * Get default sort order for orders.
     *
     * @return string
     */
    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    /**
     * Build query with common logic for filtering, sorting, and relations.
     * 
     * Uses Order model scope: filter()
     *
     * @param array $filters
     * @param array $relations
     * @return Builder
     */
    protected function buildQuery(array $filters = [], array $relations = []): Builder
    {
        $query = $this->query();

        // Apply filter scope (handles: search, status, payment_status, customer_id, date_from, date_to)
        if (method_exists(Order::class, 'scopeFilter')) {
            $query->filter($filters);
        } else {
            // Fallback if scope doesn't exist yet
            $this->applyFilters($query, $filters);
        }

        // Eager load relations
        if (!empty($relations)) {
            $query->with($relations);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? $this->getDefaultSortColumn();
        $sortOrder = $filters['sort_order'] ?? $this->getDefaultSortOrder();
        $query->orderBy($sortBy, $sortOrder);

        return $query;
    }

    /**
     * Apply filters manually (fallback if scope doesn't exist).
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                          ->orWhere('customer_name', 'like', "%{$search}%")
                          ->orWhere('customer_email', 'like', "%{$search}%")
                          ->orWhere('customer_phone', 'like', "%{$search}%")
                          ->orWhereHas('customer', function ($q) use ($search) {
                              $q->where('name', 'like', "%{$search}%");
                          });
                });
            })
            ->when($filters['status'] ?? null, fn($q, $status) => 
                $q->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn($q, $paymentStatus) => 
                $q->where('payment_status', $paymentStatus))
            ->when($filters['customer_id'] ?? null, fn($q, $customerId) => 
                $q->where('customer_id', $customerId))
            ->when($filters['date_from'] ?? null, fn($q, $date) => 
                $q->whereDate('order_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn($q, $date) => 
                $q->whereDate('order_date', '<=', $date));
    }

    /**
     * Get paginated orders with filters and relations.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        return $this->buildQuery($filters, $relations)->paginate($perPage);
    }

    /**
     * Find order by ID with relations.
     *
     * @param int $id
     * @param array $relations
     * @return Order|null
     */
    public function find(int $id, array $relations = []): ?Order
    {
        $query = $this->query();
        
        if (!empty($relations)) {
            $query->with($relations);
        }
        
        return $query->find($id);
    }

    /**
     * Find order by order number.
     *
     * @param string $orderNumber
     * @param array $relations
     * @return Order|null
     */
    public function findByOrderNumber(string $orderNumber, array $relations = []): ?Order
    {
        $query = $this->query()->where('order_number', $orderNumber);
        
        if (!empty($relations)) {
            $query->with($relations);
        }
        
        return $query->first();
    }

    /**
     * Create order.
     *
     * @param array $data
     * @return Order
     */
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    /**
     * Update order.
     *
     * @param Order $order
     * @param array $data
     * @return Order
     */
    public function update(Model $order, array $data): Order
    {
        $order->update($data);
        return $order->fresh();
    }

    /**
     * Delete order.
     *
     * @param Order $order
     * @return bool
     */
    public function delete(Model $order): bool
    {
        return $order->delete();
    }

    /**
     * Get orders by customer.
     *
     * @param int $customerId
     * @param int $limit
     * @param array $relations
     * @return Collection
     */
    public function getByCustomer(int $customerId, int $limit = 10, array $relations = []): Collection
    {
        $query = $this->query()
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);
        
        if (!empty($relations)) {
            $query->with($relations);
        }
        
        return $query->get();
    }

    /**
     * Generate unique order number.
     *
     * @return string
     */
    public function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . strtoupper(uniqid());
        } while ($this->findByOrderNumber($orderNumber));
        
        return $orderNumber;
    }

    /**
     * Get order statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'total' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'confirmed' => Order::where('status', 'confirmed')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'completed' => Order::where('status', 'completed')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'today' => Order::whereDate('created_at', today())->count(),
            'unpaid' => Order::where('payment_status', 'unpaid')->count(),
            'paid' => Order::where('payment_status', 'paid')->count(),
        ];
    }
}
