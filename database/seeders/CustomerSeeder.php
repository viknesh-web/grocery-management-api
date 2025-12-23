<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'name' => 'John Doe',
                'whatsapp_number' => '+919876543210',
                'address' => '123 Main Street, City',
                'area' => 'Downtown',
                'remarks' => 'Regular customer',
                'active' => true,
            ],
            [
                'name' => 'Jane Smith',
                'whatsapp_number' => '+919876543211',
                'address' => '456 Oak Avenue, City',
                'area' => 'Uptown',
                'remarks' => 'Prefers morning delivery',
                'active' => true,
            ],
            [
                'name' => 'Bob Johnson',
                'whatsapp_number' => '+919876543212',
                'address' => '789 Pine Road, City',
                'area' => 'Suburbs',
                'remarks' => null,
                'active' => true,
            ],
            [
                'name' => 'Alice Williams',
                'whatsapp_number' => '+919876543213',
                'address' => '321 Elm Street, City',
                'area' => 'Downtown',
                'remarks' => 'Bulk orders',
                'active' => true,
            ],
            [
                'name' => 'Charlie Brown',
                'whatsapp_number' => '+919876543214',
                'address' => '654 Maple Drive, City',
                'area' => 'Uptown',
                'remarks' => null,
                'active' => false,
            ],
        ];

        foreach ($customers as $customer) {
            \App\Models\Customer::create($customer);
        }
    }
}
