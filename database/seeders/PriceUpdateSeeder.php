<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PriceUpdateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = \App\Models\Product::all();
        
        if ($products->isEmpty()) {
            $this->command->warn('No products found. Please run ProductSeeder first.');
            return;
        }

        $user = \App\Models\User::first();
        
        if (!$user) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        // Create some sample price updates
        foreach ($products->take(3) as $product) {
            \App\Models\PriceUpdate::create([
                'product_id' => $product->id,
                'old_original_price' => $product->original_price - 10,
                'new_original_price' => $product->original_price,
                'old_discount_type' => 'none',
                'new_discount_type' => $product->discount_type,
                'old_discount_value' => null,
                'new_discount_value' => $product->discount_value,
                'old_stock_quantity' => $product->stock_quantity - 20,
                'new_stock_quantity' => $product->stock_quantity,
                'new_selling_price' => $product->selling_price,
                'updated_by' => $user->id,
                'created_at' => now()->subDays(rand(1, 7)),
            ]);
        }
    }
}
