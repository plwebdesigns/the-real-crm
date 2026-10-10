<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'password_set_at' => now(),
            'remember_token' => Str::random(10),
            'is_admin' => false,
            'is_super_admin' => false,
            'location_id' => Location::factory(),
        ];
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_admin' => true,
        ]);
    }

    /**
     * Indicate that the user is a firm-wide super administrator.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_admin' => true,
            'is_super_admin' => true,
            'location_id' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has not chosen a password yet.
     */
    public function withoutChosenPassword(): static
    {
        return $this->state(fn (array $attributes): array => [
            'password' => Str::password(),
            'password_set_at' => null,
        ]);
    }
}
