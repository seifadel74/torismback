<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@tourism.com',
            'password' => Hash::make('password123'),
            'phone' => '+201234567890',
            'address' => 'Cairo, Egypt',
            'role' => 'admin',
        ]);

        // Create regular users
        User::create([
            'name' => 'Ahmed Mohamed',
            'email' => 'ahmed@example.com',
            'password' => Hash::make('password123'),
            'phone' => '+201234567891',
            'address' => 'Alexandria, Egypt',
        ]);

        User::create([
            'name' => 'Fatima Ali',
            'email' => 'fatima@example.com',
            'password' => Hash::make('password123'),
            'phone' => '+201234567892',
            'address' => 'Giza, Egypt',
        ]);

        User::create([
            'name' => 'Omar Hassan',
            'email' => 'omar@example.com',
            'password' => Hash::make('password123'),
            'phone' => '+201234567893',
            'address' => 'Luxor, Egypt',
        ]);

        // Create more users using factory
        User::factory(10)->create();
    }
}