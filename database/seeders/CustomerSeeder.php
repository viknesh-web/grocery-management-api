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
                'email' => 'test12@gmail.com',
                'landmark' => 'Downtown',
                'remarks' => 'Regular customer',
                'status' => 'active',
            ],
            [
                'name' => 'Jane Smith',
                'whatsapp_number' => '+919876543211',
                'address' => '456 Oak Avenue, City',
                'email' => 'test123@gmail.com',
                'landmark' => 'Uptown',
                'remarks' => 'Prefers morning delivery',
                'status' => 'active',
            ],
            [
                'name' => 'Bob Johnson',
                'whatsapp_number' => '+919876543212',
                'address' => '789 Pine Road, City',
                'email' => 'test124@gmail.com',
                'landmark' => 'Suburbs',
                'remarks' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Alice Williams',
                'whatsapp_number' => '+919876543213',
                'address' => '321 Elm Street, City',
                'email' => 'test125@gmail.com',
                'landmark' => 'Downtown',
                'remarks' => 'Bulk orders',
                'status' => 'active',
            ],
            [
                'name' => 'Charlie Brown',
                'whatsapp_number' => '+919876543214',
                'address' => '654 Maple Drive, City',
                'email' => 'test129@gmail.com',
                'landmark' => 'Uptown',
                'remarks' => null,
                'status' => 'inactive',
            ],
        ];

        foreach ($customers as $customer) {
            \App\Models\Customer::create($customer);
        }
    }
}
