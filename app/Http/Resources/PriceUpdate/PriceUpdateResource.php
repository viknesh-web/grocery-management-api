<?php

namespace App\Http\Resources\PriceUpdate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceUpdateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'item_code' => $this->product->item_code,
                ];
            }),
            'old_original_price' => $this->old_original_price ? (float) $this->old_original_price : null,
            'new_original_price' => (float) $this->new_original_price,
            'old_discount_type' => $this->old_discount_type,
            'new_discount_type' => $this->new_discount_type,
            'old_discount_value' => $this->old_discount_value ? (float) $this->old_discount_value : null,
            'new_discount_value' => $this->new_discount_value ? (float) $this->new_discount_value : null,
            'old_stock_quantity' => $this->old_stock_quantity ? (float) $this->old_stock_quantity : null,
            'new_stock_quantity' => $this->new_stock_quantity ? (float) $this->new_stock_quantity : null,
            'new_selling_price' => $this->new_selling_price
                ? (float) $this->new_selling_price
                : null,
            'price_change_percentage' => $this->price_change_percentage,
            'updated_by' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
