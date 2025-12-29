<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository
{
    public function getEnabledProductsWithCategories(): Collection
    {
        return Product::with('category')->where('enabled', true)->get();
    }

    public function getCategoriesOrdered(): Collection
    {
        return Category::orderBy('name')->get();
    }

    public function getProductsByIds(array $productIds): Collection
    {
        return Product::whereIn('id', $productIds)->get();
    }
}

