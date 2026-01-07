<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Price Calculator Service - Updated for ProductDiscount
 * 
 */
class PriceCalculator
{
 
    public function getEffectivePrice(Product $product): float
    {
        return (float) $product->selling_price;
    }    
 
    public function hasDiscount(Product $product): bool
    {
        return $product->has_discount;
    }

    public function getDiscountAmount(Product $product): float
    {
        return (float) $product->discount_amount;
    }    
  
    public function getDiscountPercentage(Product $product): float
    {
        return (float) $product->discount_percentage;
    }    
  
    public function calculateSubtotal(Product $product, float $quantity): float
    {
        $price = $this->getEffectivePrice($product);
        return round($price * $quantity, 2);
    }
    
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
 
    public function formatPrice(float $price, string $currency = 'AED'): string
    {
        return $currency . ' ' . number_format($price, 2);
    }
     
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