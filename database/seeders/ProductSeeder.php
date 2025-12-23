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
                'category_id' => $categories->where('slug', 'vegetables')->first()?->id,
                'original_price' => 50.00,
                'discount_type' => 'none',
                'stock_quantity' => 100,
                'stock_unit' => 'Kg',
                'enabled' => true,
                'product_type' => 'daily',
            ],
            [
                'name' => 'Onion',
                'item_code' => 'ONI001',
                'category_id' => $categories->where('slug', 'vegetables')->first()?->id,
                'original_price' => 40.00,
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'stock_quantity' => 150,
                'stock_unit' => 'Kg',
                'enabled' => true,
                'product_type' => 'daily',
            ],
            [
                'name' => 'Apple',
                'item_code' => 'APP001',
                'category_id' => $categories->where('slug', 'fruits')->first()?->id,
                'original_price' => 120.00,
                'discount_type' => 'fixed',
                'discount_value' => 20,
                'stock_quantity' => 50,
                'stock_unit' => 'Kg',
                'enabled' => true,
                'product_type' => 'standard',
            ],
            [
                'name' => 'Banana',
                'item_code' => 'BAN001',
                'category_id' => $categories->where('slug', 'fruits')->first()?->id,
                'original_price' => 60.00,
                'discount_type' => 'none',
                'stock_quantity' => 80,
                'stock_unit' => 'Kg',
                'enabled' => true,
                'product_type' => 'daily',
            ],
            [
                'name' => 'Milk',
                'item_code' => 'MIL001',
                'category_id' => $categories->where('slug', 'dairy')->first()?->id,
                'original_price' => 55.00,
                'discount_type' => 'percentage',
                'discount_value' => 5,
                'stock_quantity' => 200,
                'stock_unit' => 'L',
                'enabled' => true,
                'product_type' => 'daily',
            ],
            [
                'name' => 'Rice',
                'item_code' => 'RIC001',
                'category_id' => $categories->where('slug', 'grains')->first()?->id,
                'original_price' => 80.00,
                'discount_type' => 'none',
                'stock_quantity' => 500,
                'stock_unit' => 'Kg',
                'enabled' => true,
                'product_type' => 'standard',
            ],
        ];

        foreach ($products as $product) {
            \App\Models\Product::create($product);
        }
    }
}
