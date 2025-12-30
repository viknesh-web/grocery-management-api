<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = \App\Models\Category::all();
        
        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Please run CategorySeeder first.');
            return;
        }

        $products = [
            [
                'name' => 'Tomato',
                'item_code' => 'TOM001',
                'category_id' => $categories->where('name', 'Vegetables')->first()?->id,
                'regular_price' => 50.00,
                'stock_quantity' => 100,
                'stock_unit' => 'Kg',
                'status' => 'active',
                'product_type' => 'daily',
            ],
            [
                'name' => 'Onion',
                'item_code' => 'ONI001',
                'category_id' => $categories->where('name', 'Vegetables')->first()?->id,
                'regular_price' => 40.00,
                'stock_quantity' => 150,
                'stock_unit' => 'Kg',
                'status' => 'active',
                'product_type' => 'daily',
            ],
            [
                'name' => 'Apple',
                'item_code' => 'APP001',
                'category_id' => $categories->where('name', 'Fruits')->first()?->id,
                'regular_price' => 120.00,
                'stock_quantity' => 50,
                'stock_unit' => 'Kg',
                'status' => 'active',
                'product_type' => 'standard',
            ],
            [
                'name' => 'Banana',
                'item_code' => 'BAN001',
                'category_id' => $categories->where('name', 'Fruits')->first()?->id,
                'regular_price' => 60.00,
                'stock_quantity' => 80,
                'stock_unit' => 'Kg',
                'status' => 'active',
                'product_type' => 'daily',
            ],
            [
                'name' => 'Milk',
                'item_code' => 'MIL001',
                'category_id' => $categories->where('name', 'Dairy')->first()?->id,
                'regular_price' => 55.00,
                'stock_quantity' => 200,
                'stock_unit' => 'L',
                'status' => 'active',
                'product_type' => 'daily',
            ],
            [
                'name' => 'Rice',
                'item_code' => 'RIC001',
                'category_id' => $categories->where('name', 'Grains')->first()?->id,
                'regular_price' => 80.00,
                'stock_quantity' => 500,
                'stock_unit' => 'Kg',
                'status' => 'active',
                'product_type' => 'standard',
            ],
        ];

        foreach ($products as $productData) {
            $product = \App\Models\Product::create($productData);
            
            // Create product discounts if needed (for products that should have discounts)
            if ($product->name === 'Onion') {
                \App\Models\ProductDiscount::create([
                    'product_id' => $product->id,
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                    'status' => 'active',
                ]);
            } elseif ($product->name === 'Apple') {
                \App\Models\ProductDiscount::create([
                    'product_id' => $product->id,
                    'discount_type' => 'fixed',
                    'discount_value' => 20,
                    'status' => 'active',
                ]);
            } elseif ($product->name === 'Milk') {
                \App\Models\ProductDiscount::create([
                    'product_id' => $product->id,
                    'discount_type' => 'percentage',
                    'discount_value' => 5,
                    'status' => 'active',
                ]);
            }
        }
    }
}
