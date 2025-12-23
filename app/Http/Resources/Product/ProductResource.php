<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Category\CategoryResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $discountActive = $this->isDiscountActive();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'item_code' => $this->item_code,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function () {
                return new CategoryResource($this->category);
            }),
            'image' => $this->image,
            'image_url' => $this->image_url,
            'original_price' => (float) $this->original_price,
            'discount_type' => $discountActive ? $this->discount_type : 'none',
            'discount_value' => $discountActive && $this->discount_value ? (float) $this->discount_value : null,
            'discount_start_date' => $this->discount_start_date?->toDateString(),
            'discount_end_date' => $this->discount_end_date?->toDateString(),
            'selling_price' => (float) $this->selling_price,
            'discount_amount' => (float) $this->discount_amount,
            'discount_percentage' => (float) $this->discount_percentage,
            'has_discount' => $this->has_discount,
            'stock_quantity' => (float) $this->stock_quantity,
            'stock_unit' => $this->stock_unit,
            'enabled' => $this->enabled,
            'product_type' => $this->product_type,
            'has_variations' => $this->hasVariations(),
            'price_range' => $this->price_range,
            'variations' => $this->whenLoaded('variations', function () {
                return $this->variations->map(function ($variation) {
                    return [
                        'id' => $variation->id,
                        'quantity' => (float) $variation->quantity,
                        'unit' => $variation->unit,
                        'display_name' => $variation->display_name,
                        'price' => (float) $variation->price,
                        'stock_quantity' => $variation->stock_quantity,
                        'sku' => $variation->sku,
                        'enabled' => $variation->enabled,
                        'is_in_stock' => $variation->isInStock(),
                    ];
                });
            }),
            'active_variations' => $this->whenLoaded('activeVariations', function () {
                return $this->activeVariations->map(function ($variation) {
                    return [
                        'id' => $variation->id,
                        'quantity' => (float) $variation->quantity,
                        'unit' => $variation->unit,
                        'display_name' => $variation->display_name,
                        'full_name' => $variation->full_name,
                        'price' => (float) $variation->price,
                        'stock_quantity' => $variation->stock_quantity,
                        'sku' => $variation->sku,
                        'enabled' => $variation->enabled,
                        'is_in_stock' => $variation->isInStock(),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_by' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'updated_by' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email,
                ];
            }),
        ];
    }
}
