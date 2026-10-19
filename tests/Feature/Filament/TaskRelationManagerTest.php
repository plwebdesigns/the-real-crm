<?php

namespace Tests\Feature\Filament;

use App\Enums\TaskType;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Sale;
use App\Models\SaleUser;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskRelationManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_agent_adds_a_task_for_themselves_on_a_lead(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $teammate = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $sale = Sale::factory()->withoutAgents()->for($lead)->create();

        Livewire::actingAs($agent)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->callTableAction('create', data: [
                'sale_id' => $sale->id,
                'user_id' => $teammate->id,
                'type' => TaskType::Email->value,
                'due_at' => '2026-10-20 10:00:00',
                'notes' => 'Email the offer summary.',
            ])
            ->assertHasNoTableActionErrors();

        $task = $lead->tasks()->first();

        $this->assertNotNull($task);
        $this->assertSame($sale->id, $task->sale_id);
        $this->assertSame($agent->id, $task->user_id);
        $this->assertSame(TaskType::Email, $task->type);
        $this->assertSame('Email the offer summary.', $task->notes);
    }

    public function test_agent_can_add_a_task_on_a_sale_they_are_assigned_to(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $sale = Sale::factory()->withoutAgents()->for($lead)->create();
        $this->assignAgent($sale, $agent);

        Livewire::actingAs($agent)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $sale,
                'pageClass' => EditSale::class,
            ])
            ->callTableAction('create', data: [
                'type' => TaskType::Call->value,
                'due_at' => '2026-10-20 10:00:00',
                'notes' => 'Confirm the walkthrough.',
            ])
            ->assertHasNoTableActionErrors();

        $task = $sale->tasks()->first();

        $this->assertNotNull($task);
        $this->assertSame($lead->id, $task->lead_id);
        $this->assertSame($sale->id, $task->sale_id);
        $this->assertSame($agent->id, $task->user_id);
        $this->assertSame('Confirm the walkthrough.', $task->notes);
    }

    public function test_agent_cannot_open_tasks_for_a_lead_at_another_office(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();

        Livewire::actingAs($agent)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->assertForbidden();

        $this->assertSame(0, Task::query()->count());
    }

    public function test_agent_cannot_open_tasks_for_a_sale_they_are_not_assigned_to(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $sale = Sale::factory()->withoutAgents()->for($lead)->create();

        Livewire::actingAs($agent)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $sale,
                'pageClass' => EditSale::class,
            ])
            ->assertForbidden();

        $this->assertSame(0, Task::query()->count());
    }

    private function assignAgent(Sale $sale, User $agent): void
    {
        $sale->agents()->attach($agent, [
            'commission_percent' => 100,
            'net_commission' => SaleUser::netCommissionFor(
                Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                100,
            ),
        ]);
    }
}
