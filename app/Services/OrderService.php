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
 * 
 * 
 * Responsibilities:
 * - Business logic orchestration
 * - Form data preparation
 * - Cart management (via CartService)
 * - Order creation with items
 * - Session management (minimal - only cart ID)
 * 
 * Does NOT contain:
 * - Direct Product/Category model queries (uses repositories)
 * - PDF generation (delegated to OrderPdfService)
 * - Price calculations (delegated to PriceCalculator)
 */
class OrderService
{
    /**
     * Session key for order confirmation data.
     */
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

    /**
     * Get form data for order form.
     * 
     * Returns products and categories for the order form.
     * 
     * @return array ['products' => Collection, 'categories' => Collection]
     */
    public function getFormData(): array
    {
        // Get active products with category relation
        $products = $this->productRepository->getActive(['category']);

        // Get all categories sorted by name
        $categories = $this->categoryRepository->all([], [])
            ->sortBy('name')
            ->values();

        return [
            'products' => $products,
            'categories' => $categories,
        ];
    }

    /**
     * Process products for review (from order form submission).
     * 
     * NEW APPROACH:
     * - Validates input
     * - Saves to CartService (lightweight cache)
     * - Returns cart ID (not full products)
     * 
     * @param array $productsInput Form input: [product_id => ['qty' => float, 'unit' => string], ...]
     * @return string Cart ID
     * @throws ValidationException If no valid products selected
     */
    public function processReview(array $productsInput): string
    {
        // Filter products with quantity > 0
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

        // Validate products exist and are active
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

        // Populate default units if not provided
        foreach ($cartItems as $productId => $item) {
            if (!isset($item['unit'])) {
                $product = $validProducts->firstWhere('id', $productId);
                $cartItems[$productId]['unit'] = $product->stock_unit;
            }
        }

        // Save to cart (lightweight - only IDs and quantities)
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
     * NEW APPROACH:
     * - Gets fresh data from DB via CartService
     * - Products include cart_qty, cart_unit, cart_subtotal
     * 
     * @param string|null $cartId
     * @return Collection Collection of products with cart attributes
     * @throws ValidationException If cart is empty
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

    /**
     * Get review quantities (for back-to-form functionality).
     * 
     * Returns raw cart items for pre-filling the form.
     * 
     * @return array ['product_id' => ['qty' => float, 'unit' => string], ...]
     */
    public function getReviewQuantities(): array
    {
        return $this->cartService->getRawCartItems();
    }

    /**
     * Check if request is from review page.
     * 
     * Used to determine if we should preserve cart data when returning to form.
     * 
     * @param string|null $fromQuery Query parameter value
     * @return bool
     */
    public function isFromReview(?string $fromQuery): bool
    {
        return $fromQuery === 'review';
    }

    /**
     * Clear cart (for starting new order).
     * 
     * @return void
     */
    public function clearSession(): void
    {
        $this->cartService->clearCart();
        Log::debug('Cart cleared');
    }

    /**
     * Create order from web form submission.
     * 
     * UPDATED LOGIC:
     * - Gets products from CartService
     * - Uses PriceCalculator for all calculations
     * - Creates OrderItemDTOs for type safety
     * - Supports unit conversions
     * - Maintains existing customer logic
     * 
     * @param array $data Order data with customer info and products
     * @return Order
     * @throws \Exception
     */
    public function createFromWebForm(array $data): Order
    {
        DB::beginTransaction();
        
        try {
            Log::info('Order creation started', [
                'customer_name' => $data['customer_name'],
            ]);
            
            // Get products from cart (if not provided in data)
            $products = $data['products'] ?? $this->cartService->getCart();
            
            if ($products->isEmpty()) {
                throw new ValidationException('No products in cart');
            }
            
            // Find or create customer
            $customer = $this->findOrCreateCustomer($data);
            
            // Generate order number
            $orderNumber = $this->orderRepository->generateOrderNumber();
            
            // Create order items DTOs (type-safe)
            $orderItems = $this->createOrderItemDTOs($products);
            
            // Calculate totals using PriceCalculator
            $totals = $this->calculateOrderTotals($orderItems);
            
            // Create order
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
            
            // Create order items from DTOs
            $itemsData = $orderItems->map(fn($dto) => $dto->toArray())->toArray();
            $this->orderItemRepository->createMultiple($order->id, $itemsData);
            
            Log::info('Order items created', ['count' => count($itemsData)]);
            
            DB::commit();
            
            // Clear cart after successful order
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

    /**
     * Create OrderItemDTOs from products.
     * 
     * Converts products (with cart attributes) into type-safe DTOs.
     * 
     * @param Collection $products Products with cart_qty and cart_unit
     * @return Collection<OrderItemDTO>
     */
    private function createOrderItemDTOs(Collection $products): Collection
    {
        return $products->map(function ($product) {
            // Get quantity and unit from cart attributes
            $quantity = $product->cart_qty ?? $product->qty ?? 0;
            $unit = $product->cart_unit ?? $product->stock_unit;
            
            // Calculate prices using PriceCalculator
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

    /**
     * Calculate order totals from OrderItemDTOs.
     * 
     * Uses DTOs for clean, type-safe calculations.
     * 
     * @param Collection<OrderItemDTO> $orderItems
     * @return array ['subtotal' => float, 'discount_amount' => float, 'total' => float]
     */
    private function calculateOrderTotals(Collection $orderItems): array
    {
        $subtotal = $orderItems->sum('subtotal');
        $discountAmount = $orderItems->sum('discountAmount');
        $total = $subtotal; // Total = subtotal (discount already applied in item prices)
        
        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Find or create customer from order data.
     * 
     * UNCHANGED - existing logic maintained.
     *
     * @param array $data
     * @return Customer|null
     */
    private function findOrCreateCustomer(array $data): ?Customer
    {
        $phone = $data['customer_phone'];
        
        // Find existing customer by phone
        $customer = $this->customerRepository->findByPhone($phone);
        
        if ($customer) {
            return $customer;
        }
        
        // Create new customer
        $customerData = [
            'name' => $data['customer_name'],
            'whatsapp_number' => $phone,
            'email' => $data['customer_email'] ?? null,
            'address' => $data['customer_address'] ?? null,
            'status' => 'active',
        ];
        
        return $this->customerRepository->create($customerData);
    }

    /**
     * Save order confirmation data to session.
     * 
     * Stores only order ID (not full order data).
     * 
     * @param array $data Order confirmation data
     * @return void
     */
    public function saveConfirmationToSession(array $data): void
    {
        session([self::SESSION_KEY_CONFIRMATION => $data]);
        Log::debug('Order confirmation saved to session');
    }

    /**
     * Get order confirmation data from session.
     * 
     * @return array|null Order confirmation data or null
     */
    public function getConfirmationFromSession(): ?array
    {
        return session(self::SESSION_KEY_CONFIRMATION);
    }

    // ===== Admin Panel Methods (Unchanged) =====

    /**
     * Get paginated orders (for API/admin).
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15)
    {
        $relations = ['customer', 'items', 'items.product'];
        return $this->orderRepository->paginate($filters, $perPage, $relations);
    }

    /**
     * Find order by ID.
     *
     * @param int $id
     * @return Order|null
     */
    public function find(int $id): ?Order
    {
        return $this->orderRepository->find($id, ['customer', 'items', 'items.product']);
    }

    /**
     * Update order status.
     *
     * @param Order $order
     * @param string $status
     * @param int $userId
     * @return Order
     */
    public function updateStatus(Order $order, string $status, int $userId): Order
    {
        return $this->orderRepository->update($order, [
            'status' => $status,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Cancel order.
     *
     * @param Order $order
     * @param string|null $reason
     * @param int $userId
     * @return Order
     */
    public function cancel(Order $order, ?string $reason, int $userId): Order
    {
        return $this->orderRepository->update($order, [
            'status' => 'cancelled',
            'updated_by' => $userId,
        ]);
    }

    /**
     * Delete order.
     *
     * @param Order $order
     * @return bool
     */
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