<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Price Calculator Service - Updated for ProductDiscount
 * 
 * Centralized price calculation logic that works with your
 * existing ProductDiscount model.
 * 
 * Eliminates duplicate price calculations across:
 * - OrderService
 * - OrderPdfService
 * - Blade templates
 * - API responses
 * 
 * Used by:
 * - OrderService
 * - CartService
 * - Blade templates (via helper)
 * - API controllers
 */
class PriceCalculator
{
    /**
     * Get the effective selling price for a product.
     * 
     * Uses Product's existing discount logic (ProductDiscount model).
     * Returns selling_price if there's an active discount, otherwise regular_price.
     *
     * @param Product $product
     * @return float
     */
    public function getEffectivePrice(Product $product): float
    {
        // Uses Product model's getSellingPriceAttribute() which handles ProductDiscount
        return (float) $product->selling_price;
    }
    
    /**
     * Check if product has an active discount.
     * 
     * Uses Product's existing discount logic.
     *
     * @param Product $product
     * @return bool
     */
    public function hasDiscount(Product $product): bool
    {
        // Uses Product model's has_discount attribute
        return $product->has_discount;
    }
    
    /**
     * Calculate the discount amount.
     * 
     * Uses Product's existing discount logic.
     *
     * @param Product $product
     * @return float
     */
    public function getDiscountAmount(Product $product): float
    {
        // Uses Product model's discount_amount attribute
        return (float) $product->discount_amount;
    }
    
    /**
     * Calculate the discount percentage.
     * 
     * Uses Product's existing discount logic.
     *
     * @param Product $product
     * @return float Percentage (e.g., 25.5 for 25.5%)
     */
    public function getDiscountPercentage(Product $product): float
    {
        // Uses Product model's discount_percentage attribute
        return (float) $product->discount_percentage;
    }
    
    /**
     * Calculate price for a given quantity (same unit as stock_unit).
     *
     * @param Product $product
     * @param float $quantity
     * @return float
     */
    public function calculateSubtotal(Product $product, float $quantity): float
    {
        $price = $this->getEffectivePrice($product);
        return round($price * $quantity, 2);
    }
    
    /**
     * Calculate price with unit conversion.
     * 
     * Example: Product is "1 kg = AED 100"
     *         Customer orders "500 g"
     *         Returns: AED 50
     *
     * Uses Product's calculatePriceForQuantity() method.
     *
     * @param Product $product
     * @param float $quantity
     * @param string $unit Customer's requested unit
     * @return float
     */
    public function calculatePriceWithUnit(Product $product, float $quantity, string $unit): float
    {
        try {
            // Use Product model's built-in unit conversion
            return $product->calculatePriceForQuantity($quantity, $unit);
        } catch (\InvalidArgumentException $e) {
            // If conversion fails, log and use base calculation
            Log::warning("Unit conversion failed for product {$product->id}", [
                'product' => $product->name,
                'quantity' => $quantity,
                'unit' => $unit,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to simple calculation
            return $this->calculateSubtotal($product, $quantity);
        }
    }
    
    /**
     * Calculate total for multiple items.
     *
     * @param array $items Array of ['product' => Product, 'quantity' => float, 'unit' => string]
     * @return array ['subtotal' => float, 'discount' => float, 'total' => float]
     */
    public function calculateCartTotal(array $items): array
    {
        $subtotal = 0;
        $totalDiscount = 0;
        
        foreach ($items as $item) {
            $product = $item['product'];
            $quantity = $item['quantity'];
            $unit = $item['unit'] ?? $product->stock_unit;
            
            // Calculate item subtotal with unit conversion
            $itemSubtotal = $this->calculatePriceWithUnit($product, $quantity, $unit);
            
            // Calculate item discount (discount amount per unit × quantity)
            // Note: This assumes discount is already applied in effective price
            // So we calculate the original price and subtract effective price
            $regularPrice = (float) $product->regular_price;
            $effectivePrice = $this->getEffectivePrice($product);
            $discountPerUnit = $regularPrice - $effectivePrice;
            
            // For unit conversions, we need to calculate the quantity in base units
            try {
                $baseUnit = strtolower($product->stock_unit);
                $requestedUnit = strtolower($unit);
                
                if ($baseUnit !== $requestedUnit) {
                    $conversionFactor = $this->getUnitConversionFactor($product, $baseUnit, $requestedUnit);
                    if ($conversionFactor !== null) {
                        $quantityInBaseUnit = $quantity / $conversionFactor;
                        $itemDiscount = $discountPerUnit * $quantityInBaseUnit;
                    } else {
                        $itemDiscount = $discountPerUnit * $quantity;
                    }
                } else {
                    $itemDiscount = $discountPerUnit * $quantity;
                }
            } catch (\Exception $e) {
                $itemDiscount = $discountPerUnit * $quantity;
            }
            
            $subtotal += $itemSubtotal;
            $totalDiscount += $itemDiscount;
        }
        
        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($totalDiscount, 2),
            'total' => round($subtotal, 2), // Total = subtotal (discount already applied)
        ];
    }
    
    /**
     * Get unit conversion factor (helper method).
     * 
     * @param Product $product
     * @param string $baseUnit
     * @param string $targetUnit
     * @return float|null
     */
    private function getUnitConversionFactor(Product $product, string $baseUnit, string $targetUnit): ?float
    {
        foreach (Product::UNIT_CONVERSIONS as $family => $data) {
            if (isset($data['conversions'][$baseUnit]) && 
                isset($data['conversions'][$targetUnit])) {
                
                $baseValue = $data['conversions'][$baseUnit];
                $targetValue = $data['conversions'][$targetUnit];
                
                return $targetValue / $baseValue;
            }
        }
        
        return null;
    }
    
    /**
     * Format price for display.
     *
     * @param float $price
     * @param string $currency
     * @return string
     */
    public function formatPrice(float $price, string $currency = 'AED'): string
    {
        return $currency . ' ' . number_format($price, 2);
    }
    
    /**
     * Get price breakdown for display.
     * 
     * Returns detailed price information including discount details.
     *
     * @param Product $product
     * @param float $quantity
     * @param string|null $unit
     * @return array
     */
    public function getPriceBreakdown(Product $product, float $quantity, ?string $unit = null): array
    {
        $unit = $unit ?? $product->stock_unit;
        
        $regularPrice = (float) $product->regular_price;
        $effectivePrice = $this->getEffectivePrice($product);
        $hasDiscount = $this->hasDiscount($product);
        
        $subtotal = $this->calculatePriceWithUnit($product, $quantity, $unit);
        
        // Calculate what the price would be without discount
        $regularSubtotal = $regularPrice * $quantity;
        if ($unit !== $product->stock_unit) {
            try {
                $regularSubtotal = $product->calculatePriceForQuantity($quantity, $unit);
                // Adjust for discount
                $regularSubtotal = $regularSubtotal / $effectivePrice * $regularPrice;
            } catch (\Exception $e) {
                // Use simple calculation
            }
        }
        
        $totalDiscount = $regularSubtotal - $subtotal;
        
        return [
            'regular_price' => round($regularPrice, 2),
            'effective_price' => round($effectivePrice, 2),
            'quantity' => $quantity,
            'unit' => $unit,
            'has_discount' => $hasDiscount,
            'discount_percentage' => $hasDiscount ? $this->getDiscountPercentage($product) : 0,
            'discount_amount' => round($totalDiscount, 2),
            'regular_subtotal' => round($regularSubtotal, 2),
            'subtotal' => round($subtotal, 2),
        ];
    }
}