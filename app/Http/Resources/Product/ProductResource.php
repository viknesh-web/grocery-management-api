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
        $activeDiscount = $this->activeDiscount();

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
            'regular_price' => (float) $this->regular_price,
            'discount_type' => $discountActive && $activeDiscount ? $activeDiscount->discount_type : 'none',
            'discount_value' => $discountActive && $activeDiscount && $activeDiscount->discount_value ? (float) $activeDiscount->discount_value : null,
            'discount_start_date' => $activeDiscount && $activeDiscount->start_date ? $activeDiscount->start_date->toDateString() : null,
            'discount_end_date' => $activeDiscount && $activeDiscount->end_date ? $activeDiscount->end_date->toDateString() : null,
            'selling_price' => (float) $this->selling_price,
            'discount_amount' => (float) $this->discount_amount,
            'discount_percentage' => (float) $this->discount_percentage,
            'has_discount' => $this->has_discount,
            'stock_quantity' => (float) $this->stock_quantity,
            'stock_unit' => $this->stock_unit,
            'status' => $this->status,
            'product_type' => $this->product_type,
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
