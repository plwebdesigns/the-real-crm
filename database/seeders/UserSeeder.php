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
        // Create my admin user
        User::factory()->admin()->create([
            'name' => 'Paul Longo',
            'email' => 'paullongo@outlook.com',
            'password' => Hash::make('Password123'),
        ]);

        // Create non-admin users
        User::factory()->count(9)->create();
    }
}
