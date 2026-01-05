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
     */
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
     * @return Order
     */
    public function createFromWebForm(array $data): Order
    {
      
        DB::beginTransaction();
        
        try {
            Log::info('Order creation started', [
                'customer_name' => $data['customer_name'],
                'product_count' => count($data['products'])
            ]);
            
           
            $customer = $this->findOrCreateCustomer($data);
            
           
            $orderNumber = $this->orderRepository->generateOrderNumber();
            
         
            $totals = $this->calculateOrderTotals($data['products']);
        
            $orderData = [
                'order_number' => $orderNumber,
                'customer_id' => $customer?->id,               
                'order_date' => now()->toDateString(),
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'total' => $data['grand_total'],
                'status' => 'pending',
            ];
            
            // REPOSITORY: Create order ✓
            $order = $this->orderRepository->create($orderData);
            
            Log::info(' ORDER CREATED', ['order_id' => $order->id]);
            
            // BUSINESS LOGIC: Prepare order items
            $items = $this->prepareOrderItems($data['products']);
            
            // REPOSITORY: Create order items ✓
            $this->orderItemRepository->createMultiple($order->id, $items);
            
            Log::info(' ORDER ITEMS CREATED', ['count' => count($items)]);
            
            // BUSINESS LOGIC: Commit transaction
            DB::commit();
            
            Log::info(' ORDER SAVED', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            
            return $order;
            
        } catch (\Exception $e) {
            // BUSINESS LOGIC: Rollback on error
            DB::rollBack();
            Log::error('ORDER FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            throw $e;
        }
    }

    /**
     * Find or create customer from order data.
     *
     * @param array $data
     * @return Customer|null
     */
    private function findOrCreateCustomer(array $data): ?Customer
    {
        $phone = $data['customer_phone'];
        
        // REPOSITORY: Find by phone
        $customer = $this->customerRepository->findByPhone($phone);
        
        if ($customer) {
            return $customer;
        }
        
        // BUSINESS LOGIC: Prepare customer data
        $customerData = [
            'name' => $data['customer_name'],
            'whatsapp_number' => $phone,
            'email' => $data['customer_email'],
            'address' => $data['customer_address'],
            'status' => 'active',
        ];
        
        // REPOSITORY: Create customer
        return $this->customerRepository->create($customerData);
    }

    /**
     * Calculate order totals from products.
     * Handles both array and object product data.
     *
     * @param mixed $products Array or Collection of products
     * @return array ['subtotal' => float, 'discount_amount' => float]
     */
    private function calculateOrderTotals($products): array
    {
        $subtotal = 0;
        $discountAmount = 0;
        
        // Convert to array if it's a collection
        if ($products instanceof Collection) {
            $products = $products->toArray();
        }
        
        foreach ($products as $product) {
            // Handle both array and object access
            $price = $this->getProductPrice($product);
            $qty = $this->getProductQuantity($product);
            
            $subtotal += ($price * $qty);
        }
        
        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
        ];
    }

    /**
     * Prepare order items from products.
     * Handles both array and object product data.
     *
     * @param mixed $products Array or Collection of products
     * @return array Array of order items
     */
    private function prepareOrderItems($products): array
    {
        $items = [];
        
        // Convert to array if it's a collection
        if ($products instanceof Collection) {
            $products = $products->toArray();
        }
        
        foreach ($products as $product) {
            $qty = $this->getProductQuantity($product);
            
            if ($qty <= 0) continue;
            
            $price = $this->getProductPrice($product);
            $productId = $this->getProductAttribute($product, 'id');
            $stockUnit = $this->getProductAttribute($product, 'stock_unit', 'Piece');
            
            $items[] = [
                'product_id' => $productId,
                'quantity' => $qty,
                'unit' => $stockUnit,
                'price' => $price,
                'subtotal' => $price * $qty,
                'total' => $price * $qty,
            ];
        }
        
        return $items;
    }

    /**
     * Get product price (handles both array and object).
     *
     * @param mixed $product
     * @return float
     */
    private function getProductPrice($product): float
    {
        if (is_array($product)) {
            // Try selling_price first, then original_price, then regular_price, default to 0
            return (float) ($product['selling_price'] ?? $product['original_price'] ?? $product['regular_price'] ?? 0);
        }
        
        if (is_object($product)) {
            return (float) ($product->selling_price ?? $product->original_price ?? $product->regular_price ?? 0);
        }
        
        return 0.0;
    }

    /**
     * Get product quantity (handles both array and object).
     *
     * @param mixed $product
     * @return int
     */
    private function getProductQuantity($product): int
    {
        if (is_array($product)) {
            return (int) ($product['qty'] ?? 0);
        }
        
        if (is_object($product)) {
            return (int) ($product->qty ?? 0);
        }
        
        return 0;
    }

    /**
     * Get product attribute (handles both array and object).
     *
     * @param mixed $product
     * @param string $attribute
     * @param mixed $default
     * @return mixed
     */
    private function getProductAttribute($product, string $attribute, $default = null)
    {
        if (is_array($product)) {
            return $product[$attribute] ?? $default;
        }
        
        if (is_object($product)) {
            return $product->$attribute ?? $default;
        }
        
        return $default;
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