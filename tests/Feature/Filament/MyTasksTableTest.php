<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\MyTasksTable;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyTasksTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_my_tasks_lists_open_tasks_assigned_to_the_signed_in_user(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $teammate = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        Task::factory()->for($lead)->for($agent)->create([
            'notes' => 'Call about the offer.',
            'due_at' => '2026-10-20 10:00:00',
        ]);
        Task::factory()->for($lead)->for($teammate)->create([
            'notes' => 'Email the teammate lead.',
        ]);
        Task::factory()->for($lead)->for($agent)->completed()->create([
            'notes' => 'Already emailed the disclosure.',
        ]);

        Livewire::actingAs($agent)
            ->test(MyTasksTable::class)
            ->assertSee('My tasks')
            ->assertSee('Call about the offer.')
            ->assertDontSee('Email the teammate lead.')
            ->assertDontSee('Already emailed the disclosure.');
    }
}
