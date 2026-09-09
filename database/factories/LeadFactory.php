<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'lead_status_id' => LeadStatus::factory(),
            'lead_source_id' => LeadSource::factory(),
            'location' => fake()->city(),
            'property_type' => fake()->randomElement(['Single Family', 'Condo', 'Townhouse', 'Land']),
            'price_range' => fake()->randomElement(['$200k-$300k', '$300k-$500k', '$500k-$750k', '$750k+']),
            'bedrooms' => fake()->numberBetween(1, 5),
            'bathrooms' => fake()->randomElement(['1.0', '1.5', '2.0', '2.5', '3.0', '3.5']),
            'garage' => fake()->boolean(),
            'pool' => fake()->boolean(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
