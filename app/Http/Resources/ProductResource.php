<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'item_code' => $this->item_code,
            
            // Category (nested resource)
            'category' => new CategoryResource($this->whenLoaded('category')),
            
            // Pricing
            'regular_price' => (float) $this->regular_price,
            'selling_price' => (float) $this->selling_price,
            'has_discount' => $this->has_discount,
            'discount' => $this->when($this->has_discount, [
                'amount' => (float) $this->discount_amount,
                'percentage' => (float) $this->discount_percentage,
            ]),
            
            // Stock
            'stock' => [
                'quantity' => (float) $this->stock_quantity,
                'unit' => $this->stock_unit,
                'status' => $this->stock_quantity > 10 ? 'in_stock' : 
                           ($this->stock_quantity > 0 ? 'low_stock' : 'out_of_stock'),
            ],
            
            // Media
            'image_url' => $this->image_url,
            
            // Metadata
            'status' => $this->status,
            'product_type' => $this->product_type,
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Optional relationships
            'creator' => new UserResource($this->whenLoaded('creator')),
            'updater' => new UserResource($this->whenLoaded('updater')),
        ];
    }
}
