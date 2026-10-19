<?php

namespace Tests\Feature\Models;

use App\Enums\LeadActivityType;
use App\Enums\TaskType;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_sale_from_another_lead_is_rejected(): void
    {
        $lead = Lead::factory()->create();
        $otherSale = Sale::factory()->withoutAgents()->create();
        $exception = null;

        try {
            Task::factory()->for($lead)->create([
                'sale_id' => $otherSale->id,
            ]);
        } catch (ValidationException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame('The sale must belong to the selected lead.', $exception->errors()['sale_id'][0]);
        $this->assertSame(0, Task::query()->count());
    }

    public function test_an_assignee_from_another_office_is_rejected(): void
    {
        $lead = Lead::factory()->create();
        $outsider = User::factory()->create();
        $exception = null;

        try {
            Task::factory()->for($lead)->for($outsider)->create();
        } catch (ValidationException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame("The assignee must be able to access the lead's office.", $exception->errors()['user_id'][0]);
        $this->assertSame(0, Task::query()->count());
    }

    public function test_a_super_admin_can_be_assigned_a_task_at_any_office(): void
    {
        $lead = Lead::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $task = Task::factory()->for($lead)->for($admin)->create();

        $this->assertTrue($task->user->is($admin));
    }

    public function test_completing_a_task_logs_one_matching_activity(): void
    {
        $this->travelTo('2026-10-15 15:04:00');

        $location = Location::factory()->create();
        $assignee = User::factory()->for($location)->create();
        $completer = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $task = Task::factory()->for($lead)->for($assignee)->create([
            'type' => TaskType::Call,
            'notes' => 'Ask about the inspection.',
        ]);

        $activity = $task->complete($completer);

        $this->assertNotNull($activity);
        $this->assertSame(LeadActivityType::Call, $activity->type);
        $this->assertSame('Ask about the inspection.', $activity->body);
        $this->assertSame($completer->id, $activity->user_id);
        $this->assertSame('2026-10-15 15:04:00', $activity->happened_at->toDateTimeString());
        $this->assertSame('2026-10-15 15:04:00', $task->completed_at?->toDateTimeString());
        $this->assertNull($task->complete($completer));
        $this->assertSame(1, $lead->activities()->count());
    }

    public function test_completing_a_task_without_notes_logs_a_follow_up_summary(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $task = Task::factory()->for($lead)->for($agent)->create([
            'type' => TaskType::Email,
            'notes' => null,
        ]);

        $activity = $task->complete($agent);

        $this->assertNotNull($activity);
        $this->assertSame(LeadActivityType::Email, $activity->type);
        $this->assertSame('Follow-up email completed.', $activity->body);
    }

    public function test_reopening_a_task_keeps_the_logged_activity(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $task = Task::factory()->for($lead)->for($agent)->create([
            'type' => TaskType::Text,
            'notes' => 'Send the listing link.',
        ]);

        $task->complete($agent);
        $task->reopen();

        $this->assertNull($task->fresh()->completed_at);
        $this->assertSame(1, $lead->activities()->count());
        $this->assertSame('Send the listing link.', $lead->activities()->first()->body);
    }

    public function test_changing_the_due_time_clears_the_reminder(): void
    {
        $task = Task::factory()->create([
            'due_at' => '2026-10-15 09:00:00',
            'reminder_sent_at' => '2026-10-15 09:05:00',
        ]);

        $task->update([
            'due_at' => '2026-10-16 09:00:00',
        ]);

        $this->assertNull($task->fresh()->reminder_sent_at);
    }

    public function test_an_authenticated_user_creates_a_task_for_themselves(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $teammate = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();

        $this->actingAs($agent);

        $task = Task::factory()->for($lead)->for($teammate)->create();

        $this->assertSame($agent->id, $task->user_id);
    }

    public function test_an_authenticated_user_cannot_change_the_assignee(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $teammate = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $task = Task::factory()->for($lead)->for($agent)->create([
            'reminder_sent_at' => '2026-10-15 09:05:00',
        ]);

        $this->actingAs($teammate);

        $task->update([
            'user_id' => $teammate->id,
            'notes' => 'Updated notes.',
        ]);

        $fresh = $task->fresh();

        $this->assertSame($agent->id, $fresh->user_id);
        $this->assertSame('Updated notes.', $fresh->notes);
        $this->assertNotNull($fresh->reminder_sent_at);
    }

    public function test_reassigning_a_task_clears_the_reminder(): void
    {
        $location = Location::factory()->create();
        $lead = Lead::factory()->recycle($location)->create();
        $assignee = User::factory()->for($location)->create();
        $replacement = User::factory()->for($location)->create();
        $task = Task::factory()->for($lead)->for($assignee)->create([
            'reminder_sent_at' => '2026-10-15 09:05:00',
        ]);

        $task->update([
            'user_id' => $replacement->id,
        ]);

        $fresh = $task->fresh();

        $this->assertNull($fresh->reminder_sent_at);
        $this->assertTrue($fresh->user->is($replacement));
    }

    public function test_deleting_a_lead_deletes_its_tasks(): void
    {
        $task = Task::factory()->create();

        $task->lead->delete();

        $this->assertModelMissing($task);
    }

    public function test_deleting_a_sale_keeps_the_task_on_the_lead(): void
    {
        $lead = Lead::factory()->create();
        $sale = Sale::factory()->withoutAgents()->for($lead)->create();
        $task = Task::factory()->for($lead)->create([
            'sale_id' => $sale->id,
        ]);

        $sale->delete();

        $this->assertModelExists($task);
        $this->assertNull($task->fresh()->sale_id);
    }
}
