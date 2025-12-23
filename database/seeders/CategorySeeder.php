<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Vegetables',
                'slug' => 'vegetables',
                'description' => 'Fresh vegetables',
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'name' => 'Fruits',
                'slug' => 'fruits',
                'description' => 'Fresh fruits',
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'name' => 'Dairy',
                'slug' => 'dairy',
                'description' => 'Dairy products',
                'is_active' => true,
                'display_order' => 3,
            ],
            [
                'name' => 'Grains',
                'slug' => 'grains',
                'description' => 'Rice, wheat, and other grains',
                'is_active' => true,
                'display_order' => 4,
            ],
            [
                'name' => 'Spices',
                'slug' => 'spices',
                'description' => 'Spices and condiments',
                'is_active' => true,
                'display_order' => 5,
            ],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::create($category);
        }
    }
}
