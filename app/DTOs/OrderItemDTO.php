<?php

namespace App\DTOs;

use App\Models\Product;
use App\Services\PriceCalculator;

/**
 * Order Item Data Transfer Object
 * 
 * Provides type-safe, immutable data structure for order items.
 * 
 * Benefits:
 * - Type safety (no more mixed array/object handling)
 * - IDE autocomplete support
 * - Clear data contracts
 * - Easy testing
 * - Validation in one place
 */
class OrderItemDTO
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $productCode,
        public readonly float $price,
        public readonly float $quantity,
        public readonly string $unit,
        public readonly float $subtotal,
        public readonly float $discountAmount,
        public readonly float $total,
        public readonly ?string $productImageUrl = null,
    ) {}
    
    /**
     * Create DTO from Product and quantity.
     *
     * @param Product $product
     * @param float $quantity
     * @param string|null $unit
     * @param PriceCalculator|null $calculator
     * @return self
     */
    public static function fromProduct(
        Product $product,
        float $quantity,
        ?string $unit = null,
        ?PriceCalculator $calculator = null
    ): self {
        $calculator = $calculator ?? app(PriceCalculator::class);
        $unit = $unit ?? $product->stock_unit;
        
        $price = $calculator->getEffectivePrice($product);
        $subtotal = $calculator->calculatePriceWithUnit($product, $quantity, $unit);
        $discountAmount = $calculator->getDiscountAmount($product) * $quantity;
        $total = $subtotal;
        
        return new self(
            productId: $product->id,
            productName: $product->name,
            productCode: $product->item_code,
            price: $price,
            quantity: $quantity,
            unit: $unit,
            subtotal: $subtotal,
            discountAmount: $discountAmount,
            total: $total,
            productImageUrl: $product->image_url,
        );
    }
    
    /**
     * Create DTO from array data.
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            productName: (string) $data['product_name'],
            productCode: (string) $data['product_code'],
            price: (float) $data['price'],
            quantity: (float) $data['quantity'],
            unit: (string) $data['unit'],
            subtotal: (float) $data['subtotal'],
            discountAmount: (float) ($data['discount_amount'] ?? 0),
            total: (float) $data['total'],
            productImageUrl: $data['product_image_url'] ?? null,
        );
    }
    
    /**
     * Convert DTO to array for database insertion.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'price' => $this->price,
            'discount_amount' => $this->discountAmount,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
        ];
    }
    
    /**
     * Convert DTO to array for API response.
     *
     * @return array
     */
    public function toApiArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_code' => $this->productCode,
            'product_image_url' => $this->productImageUrl,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discountAmount,
            'total' => $this->total,
        ];
    }
    
    /**
     * Validate order item data.
     *
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(): array
    {
        $errors = [];
        
        if ($this->quantity <= 0) {
            $errors[] = 'Quantity must be greater than 0';
        }
        
        if ($this->price < 0) {
            $errors[] = 'Price cannot be negative';
        }
        
        if ($this->total < 0) {
            $errors[] = 'Total cannot be negative';
        }
        
        if (empty($this->unit)) {
            $errors[] = 'Unit is required';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Create collection of DTOs from products.
     *
     * @param \Illuminate\Support\Collection $products Products with cart_qty and cart_unit
     * @return \Illuminate\Support\Collection<OrderItemDTO>
     */
    public static function fromProductCollection($products): \Illuminate\Support\Collection
    {
        return $products->map(function ($product) {
            return self::fromProduct(
                $product,
                $product->cart_qty ?? $product->qty ?? 0,
                $product->cart_unit ?? $product->stock_unit
            );
        });
    }
}