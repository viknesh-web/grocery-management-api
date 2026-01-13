<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cart Service
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
    
    public function addItem(int $productId, float $quantity, ?string $unit = null): string
    {
        $cartId = $this->getOrCreateCartId();
        $cart = $this->getCartData($cartId);
        
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
        
        $productIds = array_keys($cart['items']);        
        $products = $this->productRepository->findMany($productIds, ['category','discounts'])
            ->where('status', 'active'); 
        
        return $products->map(function ($product) use ($cart) {
            $cartItem = $cart['items'][$product->id];            
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

    public function getItemCount(): int
    {
        $cartId = $this->getCartId();
        if (!$cartId) {
            return 0;
        }
        
        $cart = $this->getCartData($cartId);
        return count($cart['items'] ?? []);
    }    

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
 
    public function clearCart(): void
    {
        $cartId = $this->getCartId();
        if ($cartId) {
            Cache::forget(self::CACHE_PREFIX . $cartId);
            session()->forget(self::SESSION_KEY);
        }
    }    
 
    private function getCartId(): ?string
    {
        return session(self::SESSION_KEY);
    }
  
    private function getOrCreateCartId(): string
    {
        $cartId = $this->getCartId();
        
        if (!$cartId) {
            $cartId = $this->generateCartId();
            session([self::SESSION_KEY => $cartId]);
        }
        
        return $cartId;
    }
    

    private function generateCartId(): string
    {
        return 'cart_' . uniqid('', true) . '_' . time();
    }

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

    private function saveCartData(string $cartId, array $cart): void
    {
        Cache::put(
            self::CACHE_PREFIX . $cartId,
            $cart,
            now()->addSeconds(self::CACHE_TTL)
        );
    }

    public function validateCart(): array
    {
        $products = $this->getCart();
        $errors = [];
        
        if ($products->isEmpty()) {
            $errors[] = 'Cart is empty';
            return ['valid' => false, 'errors' => $errors];
        }
        
        foreach ($products as $product) {
            if ($product->status !== 'active') {
                $errors[] = "Product '{$product->name}' is no longer available";
            }            
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }    

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