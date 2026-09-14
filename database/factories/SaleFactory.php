<?php

namespace Database\Factories;

use App\Enums\SaleType;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\SaleUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return $this
            ->afterMaking(function (Sale $sale): void {
                $sale->gross_commission = Sale::grossCommissionFor(
                    $sale->price,
                    $sale->commission_percentage,
                );
            })
            ->afterCreating(function (Sale $sale): void {
                if ($sale->agents()->exists()) {
                    return;
                }

                $sale->agents()->attach(User::factory()->create(), [
                    'commission_percent' => 100,
                    'net_commission' => SaleUser::netCommissionFor($sale->gross_commission, 100),
                ]);
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'sale_status_id' => SaleStatus::factory(),
            'sale_type' => fake()->randomElement(SaleType::cases()),
            'street_address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'price' => fake()->numberBetween(150000, 1500000),
            'commission_percentage' => '3.0',
            'closed_at' => null,
        ];
    }

    /**
     * Indicate that the sale is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sale_status_id' => SaleStatus::factory()->state([
                'name' => 'Closed',
                'slug' => 'closed',
            ]),
            'closed_at' => now(),
        ]);
    }

    /**
     * Indicate that the sale is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sale_status_id' => SaleStatus::factory()->state([
                'name' => 'Cancelled',
                'slug' => 'cancelled',
            ]),
            'closed_at' => null,
        ]);
    }

    /**
     * Indicate that the sale is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sale_status_id' => SaleStatus::factory()->state([
                'name' => 'Pending',
                'slug' => 'pending',
            ]),
            'closed_at' => null,
        ]);
    }

    /**
     * Indicate that the sale should not have agents attached.
     */
    public function withoutAgents(): static
    {
        return $this->withoutAfterCreating();
    }
}
