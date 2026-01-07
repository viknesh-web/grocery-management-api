<?php

namespace App\Services;

use App\DTOs\OrderItemDTO;
use App\Exceptions\ValidationException;
use App\Models\Customer;
use App\Models\Order;
use App\Repositories\CategoryRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Order Service - Updated Version
 */
class OrderService
{    
    private const SESSION_KEY_CONFIRMATION = 'order_confirmation';

    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private OrderRepository $orderRepository,
        private OrderItemRepository $orderItemRepository,
        private CustomerRepository $customerRepository,
        private CartService $cartService,
        private PriceCalculator $priceCalculator
    ) {}
 
    public function getFormData(): array
    {
        $products = $this->productRepository->getActive(['category']);
        $categories = $this->categoryRepository->all([], [])
            ->sortBy('name')
            ->values();

        return [
            'products' => $products,
            'categories' => $categories,
        ];
    }

    public function processReview(array $productsInput): string
    {
        $cartItems = collect($productsInput)
            ->filter(fn($item) => isset($item['qty']) && $item['qty'] > 0)
            ->map(fn($item, $productId) => [
                'qty' => (float) $item['qty'],
                'unit' => $item['unit'] ?? null,
            ])
            ->toArray();

        if (empty($cartItems)) {
            throw new ValidationException(
                'Select at least one product',
                ['products' => ['Please select at least one product with quantity greater than 0']]
            );
        }

        $productIds = array_keys($cartItems);
        $validProducts = $this->productRepository->findMany($productIds, ['category'])
            ->where('status', 'active');

        if ($validProducts->count() !== count($productIds)) {
            $invalidIds = array_diff($productIds, $validProducts->pluck('id')->toArray());
            throw new ValidationException(
                'Some products are invalid',
                ['products' => ['Invalid product IDs: ' . implode(', ', $invalidIds)]]
            );
        }

        foreach ($cartItems as $productId => $item) {
            if (!isset($item['unit'])) {
                $product = $validProducts->firstWhere('id', $productId);
                $cartItems[$productId]['unit'] = $product->stock_unit;
            }
        }

        $cartId = $this->cartService->saveCart($cartItems);

        Log::info('Order review processed', [
            'cart_id' => $cartId,
            'item_count' => count($cartItems),
        ]);

        return $cartId;
    }

    /**
     * Get review products from cart.
     * 
     */
    public function getReviewProducts(?string $cartId = null): Collection
    {
        $products = $this->cartService->getCart($cartId);
        if ($products->isEmpty()) {
            throw new ValidationException(
                'Cart is empty',
                ['cart' => ['No products in cart. Please add products first.']]
            );
        }

        return $products;
    }
 
    public function getReviewQuantities(): array
    {
        return $this->cartService->getRawCartItems();
    }

    public function isFromReview(?string $fromQuery): bool
    {
        return $fromQuery === 'review';
    }

    public function clearSession(): void
    {
        $this->cartService->clearCart();
        Log::debug('Cart cleared');
    }

    public function createFromWebForm(array $data): Order
    {
        DB::beginTransaction();
        
        try {
            Log::info('Order creation started', [
                'customer_name' => $data['customer_name'],
            ]);
            
            $products = $data['products'] ?? $this->cartService->getCart();
            
            if ($products->isEmpty()) {
                throw new ValidationException('No products in cart');
            }
            $customer = $this->findOrCreateCustomer($data);
            $orderNumber = $this->orderRepository->generateOrderNumber();            
            $orderItems = $this->createOrderItemDTOs($products);            
            $totals = $this->calculateOrderTotals($orderItems);
            
            $orderData = [
                'order_number' => $orderNumber,
                'customer_id' => $customer?->id,
                'order_date' => now()->toDateString(),
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'total' => $data['grand_total'] ?? $totals['total'],
                'status' => 'pending',
            ];
            
            $order = $this->orderRepository->create($orderData);
            
            Log::info('Order created', ['order_id' => $order->id]);            
            $itemsData = $orderItems->map(fn($dto) => $dto->toArray())->toArray();
            $this->orderItemRepository->createMultiple($order->id, $itemsData);            
            Log::info('Order items created', ['count' => count($itemsData)]);
            
            DB::commit();
            $this->cartService->clearCart();
            
            Log::info('Order completed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            
            return $order;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            throw $e;
        }
    }

    private function createOrderItemDTOs(Collection $products): Collection
    {
        return $products->map(function ($product) {
            $quantity = $product->cart_qty ?? $product->qty ?? 0;
            $unit = $product->cart_unit ?? $product->stock_unit;            
            $price = $this->priceCalculator->getEffectivePrice($product);
            $subtotal = $this->priceCalculator->calculatePriceWithUnit($product, $quantity, $unit);
            $discountAmount = $this->priceCalculator->getDiscountAmount($product) * $quantity;
            
            return new OrderItemDTO(
                productId: $product->id,
                productName: $product->name,
                productCode: $product->item_code,
                price: $price,
                quantity: $quantity,
                unit: $unit,
                subtotal: $subtotal,
                discountAmount: $discountAmount,
                total: $subtotal,
                productImageUrl: $product->image_url ?? null,
            );
        });
    }
 
    private function calculateOrderTotals(Collection $orderItems): array
    {
        $subtotal = $orderItems->sum('subtotal');
        $discountAmount = $orderItems->sum('discountAmount');
        $total = $subtotal; 
        
        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'total' => round($total, 2),
        ];
    }
 
    private function findOrCreateCustomer(array $data): ?Customer
    {
        $phone = $data['customer_phone'];        
        $customer = $this->customerRepository->findByPhone($phone);        
        if ($customer) {
            return $customer;
        }
        
        $customerData = [
            'name' => $data['customer_name'],
            'whatsapp_number' => $phone,
            'email' => $data['customer_email'] ?? null,
            'address' => $data['customer_address'] ?? null,
            'status' => 'active',
        ];
        
        return $this->customerRepository->create($customerData);
    }

    public function saveConfirmationToSession(array $data): void
    {
        session([self::SESSION_KEY_CONFIRMATION => $data]);
        Log::debug('Order confirmation saved to session');
    }
 
    public function getConfirmationFromSession(): ?array
    {
        return session(self::SESSION_KEY_CONFIRMATION);
    }

    public function getPaginated(array $filters = [], int $perPage = 15)
    {
        $relations = ['customer', 'items', 'items.product'];
        return $this->orderRepository->paginate($filters, $perPage, $relations);
    }

    public function find(int $id): ?Order
    {
        return $this->orderRepository->find($id, ['customer', 'items', 'items.product']);
    }
  
    public function updateStatus(Order $order, string $status, int $userId): Order
    {
        return $this->orderRepository->update($order, [
            'status' => $status,
            'updated_by' => $userId,
        ]);
    }

    public function cancel(Order $order, ?string $reason, int $userId): Order
    {
        return $this->orderRepository->update($order, [
            'status' => 'cancelled',
            'updated_by' => $userId,
        ]);
    }
 
    public function delete(Order $order): bool
    {
        DB::beginTransaction();
        try {
            $this->orderItemRepository->deleteByOrder($order->id);
            $result = $this->orderRepository->delete($order);
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}