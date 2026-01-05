<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cart Service
 * 
 * Lightweight cart management using cache instead of session.
 * 
 * Benefits:
 * - Only stores IDs + quantities (not full product objects)
 * - Uses Redis/Memcached for scalability
 * - Auto-expiry (no manual cleanup)
 * - Fresh product data from DB
 * - Stateless (good for horizontal scaling)
 * 
 * Storage Structure:
 * cache:cart:{cart_id} => [
 *     'items' => [
 *         product_id => ['qty' => float, 'unit' => string]
 *     ],
 *     'created_at' => timestamp,
 *     'updated_at' => timestamp,
 * ]
 */
class CartService
{
    private const CACHE_PREFIX = 'cart:';
    private const CACHE_TTL = 3600; // 1 hour
    private const SESSION_KEY = 'cart_id';
    
    public function __construct(
        private ProductRepository $productRepository,
        private PriceCalculator $priceCalculator
    ) {}
    
    /**
     * Add item to cart.
     *
     * @param int $productId
     * @param float $quantity
     * @param string|null $unit
     * @return string Cart ID
     */
    public function addItem(int $productId, float $quantity, ?string $unit = null): string
    {
        $cartId = $this->getOrCreateCartId();
        $cart = $this->getCartData($cartId);
        
        // Get product to determine default unit
        $product = $this->productRepository->find($productId);
        if (!$product) {
            throw new \InvalidArgumentException("Product not found: {$productId}");
        }
        
        $cart['items'][$productId] = [
            'qty' => $quantity,
            'unit' => $unit ?? $product->stock_unit,
        ];
        $cart['updated_at'] = now()->timestamp;
        
        $this->saveCartData($cartId, $cart);
        
        return $cartId;
    }
    
    /**
     * Update item quantity in cart.
     *
     * @param int $productId
     * @param float $quantity
     * @param string|null $unit
     * @return void
     */
    public function updateItem(int $productId, float $quantity, ?string $unit = null): void
    {
        $cartId = $this->getCartId();
        if (!$cartId) {
            return;
        }
        
        $cart = $this->getCartData($cartId);
        
        if (isset($cart['items'][$productId])) {
            $cart['items'][$productId]['qty'] = $quantity;
            
            if ($unit !== null) {
                $cart['items'][$productId]['unit'] = $unit;
            }
            
            $cart['updated_at'] = now()->timestamp;
            $this->saveCartData($cartId, $cart);
        }
    }
    
    /**
     * Remove item from cart.
     *
     * @param int $productId
     * @return void
     */
    public function removeItem(int $productId): void
    {
        $cartId = $this->getCartId();
        if (!$cartId) {
            return;
        }
        
        $cart = $this->getCartData($cartId);
        
        if (isset($cart['items'][$productId])) {
            unset($cart['items'][$productId]);
            $cart['updated_at'] = now()->timestamp;
            $this->saveCartData($cartId, $cart);
        }
    }
    
    /**
     * Save entire cart (bulk operation).
     * Used when processing order form submission.
     *
     * @param array $items ['product_id' => ['qty' => float, 'unit' => string], ...]
     * @return string Cart ID
     */
    public function saveCart(array $items): string
    {
        $cartId = $this->getOrCreateCartId();
        
        $cart = [
            'items' => $items,
            'created_at' => now()->timestamp,
            'updated_at' => now()->timestamp,
        ];
        
        $this->saveCartData($cartId, $cart);
        
        return $cartId;
    }
    
    /**
     * Get cart items as Product collection with quantities.
     * 
     * Returns fresh data from DB + quantities from cache.
     *
     * @param string|null $cartId
     * @return Collection<Product>
     */
    public function getCart(?string $cartId = null): Collection
    {
        $cartId = $cartId ?? $this->getCartId();
        
        if (!$cartId) {
            return collect();
        }
        
        $cart = $this->getCartData($cartId);
        
        if (empty($cart['items'])) {
            return collect();
        }
        
        // Get product IDs
        $productIds = array_keys($cart['items']);
        
        // Fetch products from DB (always fresh data)
        $products = $this->productRepository->findMany($productIds, ['category'])
            ->where('status', 'active'); // Only active products
        
        // Attach cart quantities and calculate prices
        return $products->map(function ($product) use ($cart) {
            $cartItem = $cart['items'][$product->id];
            
            // Add cart-specific attributes
            $product->cart_qty = $cartItem['qty'];
            $product->cart_unit = $cartItem['unit'];
            $product->cart_price = $this->priceCalculator->getEffectivePrice($product);
            $product->cart_subtotal = $this->priceCalculator->calculatePriceWithUnit(
                $product,
                $cartItem['qty'],
                $cartItem['unit']
            );
            
            return $product;
        })->values();
    }
    
    /**
     * Get cart items count.
     *
     * @return int
     */
    public function getItemCount(): int
    {
        $cartId = $this->getCartId();
        if (!$cartId) {
            return 0;
        }
        
        $cart = $this->getCartData($cartId);
        return count($cart['items'] ?? []);
    }
    
    /**
     * Get cart totals.
     *
     * @return array ['subtotal' => float, 'discount' => float, 'total' => float, 'item_count' => int]
     */
    public function getCartTotals(): array
    {
        $products = $this->getCart();
        
        if ($products->isEmpty()) {
            return [
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
                'item_count' => 0,
            ];
        }
        
        $items = $products->map(fn($p) => [
            'product' => $p,
            'quantity' => $p->cart_qty,
            'unit' => $p->cart_unit,
        ])->toArray();
        
        $totals = $this->priceCalculator->calculateCartTotal($items);
        $totals['item_count'] = $products->count();
        
        return $totals;
    }
    
    /**
     * Clear cart.
     *
     * @return void
     */
    public function clearCart(): void
    {
        $cartId = $this->getCartId();
        if ($cartId) {
            Cache::forget(self::CACHE_PREFIX . $cartId);
            session()->forget(self::SESSION_KEY);
        }
    }
    
    /**
     * Get cart ID from session or parameter.
     *
     * @return string|null
     */
    private function getCartId(): ?string
    {
        return session(self::SESSION_KEY);
    }
    
    /**
     * Get or create cart ID.
     *
     * @return string
     */
    private function getOrCreateCartId(): string
    {
        $cartId = $this->getCartId();
        
        if (!$cartId) {
            $cartId = $this->generateCartId();
            session([self::SESSION_KEY => $cartId]);
        }
        
        return $cartId;
    }
    
    /**
     * Generate unique cart ID.
     *
     * @return string
     */
    private function generateCartId(): string
    {
        return 'cart_' . uniqid('', true) . '_' . time();
    }
    
    /**
     * Get cart data from cache.
     *
     * @param string $cartId
     * @return array
     */
    private function getCartData(string $cartId): array
    {
        $data = Cache::get(self::CACHE_PREFIX . $cartId);
        
        if (!$data) {
            return [
                'items' => [],
                'created_at' => now()->timestamp,
                'updated_at' => now()->timestamp,
            ];
        }
        
        return $data;
    }
    
    /**
     * Save cart data to cache.
     *
     * @param string $cartId
     * @param array $cart
     * @return void
     */
    private function saveCartData(string $cartId, array $cart): void
    {
        Cache::put(
            self::CACHE_PREFIX . $cartId,
            $cart,
            now()->addSeconds(self::CACHE_TTL)
        );
    }
    
    /**
     * Validate cart items (check product availability, stock).
     * 
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateCart(): array
    {
        $products = $this->getCart();
        $errors = [];
        
        if ($products->isEmpty()) {
            $errors[] = 'Cart is empty';
            return ['valid' => false, 'errors' => $errors];
        }
        
        foreach ($products as $product) {
            // Check if product is still active
            if ($product->status !== 'active') {
                $errors[] = "Product '{$product->name}' is no longer available";
            }
            
            // Add more validation as needed (stock check, etc.)
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Get raw cart items (for form submission).
     * 
     * @return array ['product_id' => ['qty' => float, 'unit' => string], ...]
     */
    public function getRawCartItems(): array
    {
        $cartId = $this->getCartId();
        if (!$cartId) {
            return [];
        }
        
        $cart = $this->getCartData($cartId);
        return $cart['items'] ?? [];
    }
}