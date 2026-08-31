<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Lead;
use App\Models\Sale;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(LookupSeeder::class);

        User::factory()->admin()->create([
            'name' => 'Paul Longo',
            'email' => 'paullongo@outlook.com',
            'password' => Hash::make('Password123'),
        ]);

        // Create some sample leads and sales
        Lead::factory(20)->create();
        Sale::factory(10)->create();
    }
}
