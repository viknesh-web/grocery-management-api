<?php

namespace App\Http\Resources\Category;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Product\ProductCollection;

class CategoryResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', function () {
                return new CategoryResource($this->parent);
            }),
            'children' => $this->whenLoaded('children', function () {
                return CategoryResource::collection($this->children);
            }),
            'products_count' => $this->products_count,
            'is_parent' => $this->is_parent,
            'full_path' => $this->full_path,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            // 'created_by' => $this->whenLoaded('creator', function () {
            //     return [
            //         'id' => $this->creator->id,
            //         'name' => $this->creator->name,
            //         'email' => $this->creator->email,
            //     ];
            // }),
            // 'updated_by' => $this->whenLoaded('updater', function () {
            //     return [
            //         'id' => $this->updater->id,
            //         'name' => $this->updater->name,
            //         'email' => $this->updater->email,
            //     ];
            // }),
            'products' => $this->whenLoaded('products', function () {
                return new ProductCollection($this->products);
            }),
        ];
    }
}
