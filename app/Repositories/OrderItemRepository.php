<?php

namespace App\Repositories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * Order Item Repository
 * 
 * Handles all database operations for order items.
 * Follows Repository pattern: data access only, no business logic.
 * 
 * Responsibilities:
 * - CRUD operations for order items
 * - Bulk operations
 */
class OrderItemRepository
{
    /**
     * Create order item.
     *
     * @param array $data
     * @return OrderItem
     */
    public function create(array $data): OrderItem
    {
        return OrderItem::create($data);
    }

    /**
     * Create multiple order items at once.
     *
     * @param int $orderId
     * @param array $items
     * @return Collection
     */
    public function createMultiple(int $orderId, array $items): Collection
    {
        $orderItems = new Collection();
        
        foreach ($items as $itemData) {
            $itemData['order_id'] = $orderId;
            $orderItems->push($this->create($itemData));
        }
        
        return $orderItems;
    }

    /**
     * Get items by order ID.
     *
     * @param int $orderId
     * @param array $relations
     * @return Collection
     */
    public function getByOrder(int $orderId, array $relations = []): Collection
    {
        $query = OrderItem::where('order_id', $orderId);
        
        if (!empty($relations)) {
            $query->with($relations);
        }
        
        return $query->get();
    }

    /**
     * Delete all items for an order.
     *
     * @param int $orderId
     * @return int Number of deleted items
     */
    public function deleteByOrder(int $orderId): int
    {
        return OrderItem::where('order_id', $orderId)->delete();
    }

    /**
     * Update order item.
     *
     * @param OrderItem $orderItem
     * @param array $data
     * @return OrderItem
     */
    public function update(OrderItem $orderItem, array $data): OrderItem
    {
        $orderItem->update($data);
        return $orderItem->fresh();
    }

    /**
     * Delete order item.
     *
     * @param OrderItem $orderItem
     * @return bool
     */
    public function delete(OrderItem $orderItem): bool
    {
        return $orderItem->delete();
    }
}


