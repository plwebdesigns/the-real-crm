<?php

namespace Tests\Feature\Filament;

use App\Enums\LeadActivityType;
use App\Enums\TaskType;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_due_picker_does_not_include_seconds(): void
    {
        $agent = User::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateTask::class)
            ->assertSeeHtml('type="datetime-local"')
            ->assertDontSeeHtml('aria-label="Second"')
            ->assertDontSeeHtml('step="1"');
    }

    public function test_agent_creates_a_task_for_themselves_on_a_lead_and_sale(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $teammate = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $sale = Sale::factory()->withoutAgents()->for($lead)->create();

        Livewire::actingAs($agent)
            ->test(CreateTask::class)
            ->assertDontSee('Assignee')
            ->assertDontSee($teammate->name)
            ->fillForm([
                'lead_id' => $lead->id,
                'sale_id' => $sale->id,
                'user_id' => $teammate->id,
                'type' => TaskType::Text,
                'due_at' => '2026-10-20 10:00:00',
                'notes' => 'Send the listing link.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Task::class, [
            'lead_id' => $lead->id,
            'sale_id' => $sale->id,
            'user_id' => $agent->id,
            'type' => TaskType::Text,
            'notes' => 'Send the listing link.',
        ]);
    }

    public function test_agent_can_open_a_task_at_their_office(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);
        $task = Task::factory()->for($lead)->for($agent)->create([
            'type' => TaskType::Call,
        ]);

        $this->actingAs($agent)
            ->get(TaskResource::getUrl('edit', ['record' => $task], isAbsolute: false))
            ->assertOk()
            ->assertSee('Call for Ada Lovelace');
    }

    public function test_agent_cannot_create_a_task_for_a_lead_at_another_office(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateTask::class)
            ->fillForm([
                'lead_id' => $lead->id,
                'type' => TaskType::Call,
                'due_at' => '2026-10-20 10:00:00',
                'notes' => 'Call the other office.',
            ])
            ->call('create')
            ->assertHasFormErrors(['lead_id']);

        $this->assertSame(0, Task::query()->count());
    }

    public function test_agent_cannot_attach_a_sale_from_another_lead(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $otherSale = Sale::factory()->withoutAgents()->create();

        Livewire::actingAs($agent)
            ->test(CreateTask::class)
            ->fillForm([
                'lead_id' => $lead->id,
                'sale_id' => $otherSale->id,
                'type' => TaskType::Email,
                'due_at' => '2026-10-20 10:00:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['sale_id']);

        $this->assertSame(0, Task::query()->count());
    }

    public function test_open_tasks_are_listed_and_completed_tasks_are_not(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        Task::factory()->for($lead)->for($agent)->create([
            'notes' => 'Call about the offer.',
        ]);
        Task::factory()->for($lead)->for($agent)->completed()->create([
            'notes' => 'Already emailed the disclosure.',
        ]);

        Livewire::actingAs($agent)
            ->test(ListTasks::class)
            ->assertSee('Call about the offer.')
            ->assertDontSee('Already emailed the disclosure.')
            ->set('activeTab', 'completed')
            ->assertSee('Already emailed the disclosure.')
            ->assertDontSee('Call about the offer.');
    }

    public function test_agent_does_not_see_tasks_for_another_office(): void
    {
        $agent = User::factory()->create();
        Task::factory()->create([
            'notes' => 'Call the other office lead.',
        ]);

        Livewire::actingAs($agent)
            ->test(ListTasks::class)
            ->assertDontSee('Call the other office lead.');
    }

    public function test_agent_does_not_find_a_task_for_another_office(): void
    {
        $agent = User::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($agent)
            ->get(TaskResource::getUrl('edit', ['record' => $task], isAbsolute: false))
            ->assertNotFound();
    }

    public function test_completing_a_task_from_the_list_logs_the_activity(): void
    {
        $this->travelTo('2026-10-15 15:04:00');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $task = Task::factory()->for($lead)->for($agent)->create([
            'type' => TaskType::Call,
            'notes' => 'Ask about the inspection.',
            'due_at' => '2026-10-14 09:00:00',
        ]);

        Livewire::actingAs($agent)
            ->test(ListTasks::class)
            ->callTableAction('complete', $task)
            ->assertHasNoTableActionErrors();

        $task->refresh();
        $activity = $lead->activities()->first();

        $this->assertNotNull($task->completed_at);
        $this->assertNotNull($activity);
        $this->assertSame(LeadActivityType::Call, $activity->type);
        $this->assertSame('Ask about the inspection.', $activity->body);
        $this->assertSame($agent->id, $activity->user_id);
    }

    public function test_completing_a_task_from_the_edit_page_logs_the_activity(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $task = Task::factory()->for($lead)->for($agent)->create([
            'type' => TaskType::Text,
            'notes' => 'Text the showing time.',
        ]);

        Livewire::actingAs($agent)
            ->test(EditTask::class, [
                'record' => $task->getRouteKey(),
            ])
            ->callAction('complete')
            ->assertHasNoActionErrors();

        $activity = $lead->activities()->first();

        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertNotNull($activity);
        $this->assertSame(LeadActivityType::Text, $activity->type);
        $this->assertSame('Text the showing time.', $activity->body);
    }

    public function test_editing_a_task_does_not_change_the_assignee(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $teammate = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $task = Task::factory()->for($lead)->for($teammate)->create([
            'notes' => 'Call about the offer.',
        ]);

        Livewire::actingAs($agent)
            ->test(EditTask::class, [
                'record' => $task->getRouteKey(),
            ])
            ->assertDontSee('Assignee')
            ->assertDontSee($teammate->name)
            ->fillForm([
                'user_id' => $agent->id,
                'notes' => 'Call about the revised offer.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $task->fresh();

        $this->assertSame($teammate->id, $fresh->user_id);
        $this->assertSame('Call about the revised offer.', $fresh->notes);
    }
}
