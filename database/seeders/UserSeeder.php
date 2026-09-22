<?php

namespace Database\Seeders;

use App\Models\Location;
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
        $downtown = Location::query()->where('name', 'Downtown')->firstOrFail();
        $westside = Location::query()->where('name', 'Westside')->firstOrFail();

        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('Password123'),
        ]);

        User::factory()->admin()->for($downtown)->create([
            'name' => 'Downtown Admin',
            'email' => 'downtownadmin@example.com',
            'password' => Hash::make('Password123'),
        ]);

        User::factory()->admin()->for($westside)->create([
            'name' => 'Westside Admin',
            'email' => 'westsideadmin@example.com',
            'password' => Hash::make('Password123'),
        ]);

        User::factory()->for($downtown)->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => Hash::make('Password123'),
        ]);

        User::factory()->count(4)->for($downtown)->create();
        User::factory()->count(4)->for($westside)->create();
    }
}
