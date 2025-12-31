<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PriceUpdateResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'old_price' => (float) $this->old_regular_price,
            'new_price' => (float) $this->new_regular_price,
            'old_stock' => (float) $this->old_stock_quantity,
            'new_stock' => (float) $this->new_stock_quantity,
            'price_change_percentage' => $this->price_change_percentage,
            'updated_by' => new UserResource($this->whenLoaded('updater')),
            'updated_at' => $this->created_at?->toISOString(),
        ];
    }
}
