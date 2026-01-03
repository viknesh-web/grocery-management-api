<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Helpers\PhoneNumberHelper;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\CategoryRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Order Service
 * 
 * Handles all business logic for order form operations.
 * 
 * Responsibilities:
 * - Business logic orchestration
 * - Form data preparation
 * - Product review processing
 * - Session management
 * - Input validation
 * 
 * Does NOT contain:
 * - Direct Product/Category model queries (uses repositories)
 * - PDF generation (delegated to OrderPdfService)
 */
class OrderService
{
    /**
     * Session key for review products.
     */
    private const SESSION_KEY_REVIEW_PRODUCTS = 'review_products';

    /**
     * Session key for review quantities.
     */
    private const SESSION_KEY_REVIEW_QTY = 'review_qty';

    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private OrderRepository $orderRepository,
        private OrderItemRepository $orderItemRepository,
        private CustomerRepository $customerRepository
    ) {}

    /**
     * Get form data for order form.
     * 
     * Handles:
     * - Product retrieval (via ProductRepository)
     * - Category retrieval (via CategoryRepository)
     * - Relation eager loading
     *
     * @return array Form data with products and categories
     */
    public function getFormData(): array
    {
        // Get enabled products with categories (business logic - data retrieval via repository)
        $products = $this->productRepository->getActive(['category']);

        // Get categories ordered by name (business logic - data retrieval via repository)
        $categories = $this->categoryRepository->all([], [])
            ->sortBy('name')
            ->values();

        return [
            'products' => $products,
            'categories' => $categories,
        ];
    }

    /**
     * Process products for review.
     * 
     * Handles:
     * - Input filtering (only products with qty > 0)
     * - Product retrieval (via ProductRepository)
     * - Quantity assignment
     * - Validation
     *
     * @param array $productsInput Form input: [product_id => ['qty' => int], ...]
     * @return Collection Collection of products with qty attribute
     * @throws ValidationException If no valid products selected
     */
    public function processReview(array $productsInput): Collection
    {
        // Filter products with quantity > 0 (business logic - input validation)
        $filtered = collect($productsInput)->filter(fn($p) => isset($p['qty']) && $p['qty'] > 0);
        
        if ($filtered->isEmpty()) {
            throw new ValidationException(
                'Select at least one product',
                ['products' => ['Please select at least one product with quantity greater than 0']]
            );
        }
        
        // Get product IDs (business logic - data extraction)
        $productIds = $filtered->keys()->map(fn($id) => (int) $id)->toArray();

        // Get products via repository (business logic - data retrieval)
        $products = $this->productRepository->findMany($productIds, ['category']);

        // Assign quantities to products (business logic - data transformation)
        $products->each(function ($product) use ($filtered) {
            $product->qty = (int) ($filtered[$product->id]['qty'] ?? 0);
        });

        Log::info('Order review processed', [
            'product_count' => $products->count(),
            'product_ids' => $productIds,
        ]);

        return $products;
    }

    /**
     * Get review products from session.
     * 
     * Handles:
     * - Session retrieval
     * - Data normalization (array to collection)
     * - Validation
     *
     * @return Collection Collection of products from session
     * @throws ValidationException If no products in session
     */
    public function getReviewProducts(): Collection
    {
        $products = session(self::SESSION_KEY_REVIEW_PRODUCTS);
        
        if (!$products || (is_countable($products) && count($products) === 0)) {
            throw new ValidationException(
                'No products in review. Please select products first.',
                ['products' => ['No products selected for review']]
            );
        }
        
        // Normalize to collection (business logic - data normalization)
        if (is_array($products)) {
            $products = collect($products);
        }
        
        return $products;
    }

    /**
     * Clear order session data.
     * 
     * Business logic: Removes all order-related session data.
     *
     * @return void
     */
    public function clearSession(): void
    {
        session()->forget([
            self::SESSION_KEY_REVIEW_PRODUCTS,
            self::SESSION_KEY_REVIEW_QTY,
        ]);

        Log::debug('Order session cleared');
    }

    /**
     * Save review data to session.
     * 
     * Business logic: Stores products and quantities in session for later use.
     *
     * @param Collection $products Products collection
     * @param array $reviewQty Quantities array: [product_id => ['qty' => int], ...]
     * @return void
     */
    public function saveReviewToSession(Collection $products, array $reviewQty): void
    {
        session([
            self::SESSION_KEY_REVIEW_PRODUCTS => $products,
            self::SESSION_KEY_REVIEW_QTY => $reviewQty,
        ]);

        Log::debug('Order review saved to session', [
            'product_count' => $products->count(),
        ]);
    }

    /**
     * Get review quantities from session.
     * 
     * Business logic: Retrieves review quantities from session.
     *
     * @return array Review quantities array
     */
    public function getReviewQuantities(): array
    {
        return session(self::SESSION_KEY_REVIEW_QTY, []);
    }

    /**
     * Check if request is from review page.
     * 
     * Business logic: Determines if request came from review page.
     *
     * @param string|null $fromQuery Query parameter value
     * @return bool
     */
    public function isFromReview(?string $fromQuery): bool
    {
        return $fromQuery === 'review';
    }

    /**
     * Save order confirmation data to session.
     * 
     * Business logic: Stores order confirmation data in session.
     *
     * @param array $data Order confirmation data
     * @return void
     */
    public function saveConfirmationToSession(array $data): void
    {
        session(['order_confirmation' => $data]);

        Log::debug('Order confirmation saved to session');
    }

    /**
     * Get order confirmation data from session.
     * 
     * Business logic: Retrieves order confirmation data from session.
     *
     * @return array|null Order confirmation data or null
     */
    public function getConfirmationFromSession(): ?array
    {
        return session('order_confirmation');
    }

    /**
     * Create order from web form submission.
     *
     * @param array $data Order data with customer info and products
     * @param int|null $userId User ID if created by admin
     * @return Order
     */
    public function createFromWebForm(array $data, ?int $userId = null): Order
    {
        DB::beginTransaction();
        try {
            // Step 1: Find or create customer
            $customer = $this->findOrCreateCustomer($data);
            
            // Step 2: Generate order number
            $orderNumber = $this->orderRepository->generateOrderNumber();
            
            // Step 3: Prepare order data
            $orderData = [
                'order_number' => $orderNumber,
                'customer_id' => $customer?->id,
                'customer_name' => $data['customer_name'] ?? $customer?->name,
                'customer_email' => $data['customer_email'] ?? $data['email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? $data['whatsapp'] ?? $customer?->whatsapp_number,
                'customer_address' => $data['customer_address'] ?? $data['address'] ?? $customer?->address,
                'order_date' => now()->toDateString(),
                'subtotal' => $data['subtotal'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'total_amount' => $data['grand_total'] ?? $data['total_amount'] ?? 0,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ];
            
            // Step 4: Create order
            $order = $this->orderRepository->create($orderData);
            
            // Step 5: Prepare and create order items
            $items = $this->prepareOrderItems($data['products'] ?? []);
            $this->orderItemRepository->createMultiple($order->id, $items);
            
            // Step 6: Reload order with relationships
            $order = $this->orderRepository->find($order->id, ['items', 'customer']);
            
            DB::commit();
            
            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_id' => $customer?->id,
            ]);
            
            return $order;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Find existing customer or create new one.
     *
     * @param array $data
     * @return Customer|null
     */
    private function findOrCreateCustomer(array $data): ?Customer
    {
        // 1. Check if customer_id provided
        if (isset($data['customer_id']) && $data['customer_id']) {
            return $this->customerRepository->find($data['customer_id']);
        }
        
        // 2. Try to find by phone
        $phone = $data['customer_phone'] ?? $data['whatsapp'] ?? null;
        if ($phone) {
            $normalizedPhone = PhoneNumberHelper::normalize($phone);
            $customers = $this->customerRepository->search($normalizedPhone);
            $customer = $customers->firstWhere('whatsapp_number', $normalizedPhone);
            if ($customer) {
                return $customer;
            }
        }
        
        // 3. Create new customer if we have required data
        $name = $data['customer_name'] ?? null;
        if ($name && $phone) {
            try {
                $customerData = [
                    'name' => trim($name),
                    'whatsapp_number' => PhoneNumberHelper::normalize($phone),
                    'address' => $data['customer_address'] ?? $data['address'] ?? null,
                    'remarks' => 'Auto-created from web order',
                    'status' => 'active',
                ];
                
                return $this->customerRepository->create($customerData);
            } catch (\Exception $e) {
                Log::warning('Failed to auto-create customer', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
            }
        }
        
        // Return null for guest order
        return null;
    }

    /**
     * Prepare order items data from products.
     *
     * @param array $products
     * @return array
     */
    private function prepareOrderItems(array $products): array
    {
        $items = [];
        
        foreach ($products as $productId => $productData) {
            // Handle both array formats:
            // 1. [$productId => ['qty' => 2]]
            // 2. [['product_id' => 1, 'qty' => 2]]
            
            if (is_array($productData) && isset($productData['product_id'])) {
                $productId = $productData['product_id'];
            }
            
            // Skip if no quantity
            $qty = $productData['qty'] ?? $productData['quantity'] ?? 0;
            if ($qty <= 0) {
                continue;
            }
            
            // Get product details
            $product = $this->productRepository->find((int) $productId);
            if (!$product) {
                Log::warning('Product not found for order item', ['product_id' => $productId]);
                continue;
            }
            
            $quantity = (float) $qty;
            $price = (float) ($productData['price'] ?? $product->selling_price ?? $product->regular_price);
            $subtotal = $quantity * $price;
            
            // Calculate discount
            $discountAmount = 0;
            $discountType = 'none';
            $discountValue = 0;
            
            // Check if product has active discount
            if ($product->has_discount ?? false) {
                $activeDiscount = $product->activeDiscount();
                if ($activeDiscount) {
                    $discountType = $activeDiscount->discount_type;
                    $discountValue = (float) $activeDiscount->discount_value;
                    
                    if ($discountType === 'percentage') {
                        $discountAmount = $subtotal * ($discountValue / 100);
                    } elseif ($discountType === 'fixed') {
                        $discountAmount = $discountValue;
                    }
                }
            }
            
            $total = $subtotal - $discountAmount;
            
            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->item_code,
                'quantity' => $quantity,
                'unit' => $product->stock_unit,
                'price' => $price,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => round($discountAmount, 2),
                'subtotal' => round($subtotal, 2),
                'total' => round($total, 2),
            ];
        }
        
        return $items;
    }

    /**
     * Get paginated orders (for API).
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
        $adminNotes = $order->admin_notes ?? '';
        if ($reason) {
            $adminNotes .= "\nCancelled: " . $reason;
        }
        
        return $this->orderRepository->update($order, [
            'status' => 'cancelled',
            'admin_notes' => $adminNotes,
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
