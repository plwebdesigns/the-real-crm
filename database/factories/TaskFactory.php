<?php

namespace Database\Factories;

use App\Enums\TaskType;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'sale_id' => null,
            'user_id' => function (array $attributes): int {
                $lead = Lead::query()->findOrFail($attributes['lead_id']);

                return User::factory()->create([
                    'location_id' => $lead->location_id,
                ])->id;
            },
            'type' => fake()->randomElement(TaskType::cases()),
            'due_at' => now()->addDays(2),
            'notes' => fake()->optional()->sentence(),
            'completed_at' => null,
            'reminder_sent_at' => null,
        ];
    }

    /**
     * Indicate that the follow-up has been completed.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'completed_at' => now(),
        ]);
    }
}
