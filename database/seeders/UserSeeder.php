<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@example.com',
                'password' => bcrypt('password'),
                'role' => 'manager',
            ],
            [
                'name' => 'Staff User',
                'email' => 'staff@example.com',
                'password' => bcrypt('password'),
                'role' => 'staff',
            ],
        ];

        foreach ($users as $user) {
            \App\Models\User::create($user);
        }
    }
}
