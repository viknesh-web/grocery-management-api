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
                'description' => 'Fresh vegetables',
                'status' => 'active',
            ],
            [
                'name' => 'Fruits',
                'description' => 'Fresh fruits',
                'status' => 'active',
            ],
            [
                'name' => 'Dairy',
                'description' => 'Dairy products',
                'status' => 'active',
            ],
            [
                'name' => 'Grains',
                'description' => 'Rice, wheat, and other grains',
                'status' => 'active',
            ],
            [
                'name' => 'Spices',
                'description' => 'Spices and condiments',
                'status' => 'active',
            ],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::create($category);
        }
    }
}
