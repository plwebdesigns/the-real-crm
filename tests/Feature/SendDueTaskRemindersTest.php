<?php

namespace Tests\Feature;

use App\Enums\TaskType;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDueNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class SendDueTaskRemindersTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_due_tasks_notify_the_assignee_once(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        Notification::fake();

        $assignee = User::factory()->create([
            'receives_task_reminder_emails' => true,
        ]);
        $lead = Lead::factory()->recycle($assignee->location)->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);
        $task = Task::factory()->for($lead)->for($assignee)->create([
            'type' => TaskType::Call,
            'due_at' => '2026-10-15 09:00:00',
            'notes' => 'Ask about the inspection.',
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();
        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        Notification::assertSentToTimes($assignee, TaskDueNotification::class, 1);
        Notification::assertSentTo($assignee, TaskDueNotification::class, function (TaskDueNotification $notification, array $channels) use ($assignee, $task): bool {
            $mail = $notification->toMail($assignee);
            $html = (string) $mail->render();

            $this->assertSame(['database', 'mail'], $channels);
            $this->assertSame('Follow-up call due for Ada Lovelace', $mail->subject);
            $this->assertStringContainsString('Ask about the inspection.', $html);
            $this->assertStringContainsString('/tasks/'.$task->id.'/edit', $html);

            return true;
        });
        $this->assertNotNull($task->fresh()->reminder_sent_at);
    }

    public function test_due_tasks_are_not_emailed_unless_the_assignee_asks_for_it(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        Notification::fake();

        $assignee = User::factory()->create();
        $lead = Lead::factory()->recycle($assignee->location)->create();
        Task::factory()->for($lead)->for($assignee)->create([
            'due_at' => '2026-10-15 09:00:00',
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        Notification::assertSentTo($assignee, TaskDueNotification::class, function (TaskDueNotification $notification, array $channels): bool {
            $this->assertSame(['database'], $channels);

            return true;
        });
    }

    public function test_due_tasks_are_stored_for_the_assignee(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        $assignee = User::factory()->create();
        $lead = Lead::factory()->recycle($assignee->location)->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);
        $task = Task::factory()->for($lead)->for($assignee)->create([
            'type' => TaskType::Call,
            'due_at' => '2026-10-15 09:00:00',
            'notes' => 'Ask about the inspection.',
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        $notification = $assignee->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('filament', $notification->data['format']);
        $this->assertSame('Follow-up call due for Ada Lovelace', $notification->data['title']);
        $this->assertSame('Ask about the inspection.', $notification->data['body']);
        $this->assertSame('View task', $notification->data['actions'][0]['label']);
        $this->assertStringEndsWith('/tasks/'.$task->id.'/edit', $notification->data['actions'][0]['url']);
    }

    public function test_completed_and_future_tasks_are_not_emailed(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        Notification::fake();

        $assignee = User::factory()->create();
        $lead = Lead::factory()->recycle($assignee->location)->create();
        Task::factory()->for($lead)->for($assignee)->completed()->create([
            'due_at' => '2026-10-15 09:00:00',
        ]);
        Task::factory()->for($lead)->for($assignee)->create([
            'due_at' => '2026-10-16 09:00:00',
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_changing_the_due_time_emails_the_assignee_again(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        Notification::fake();

        $assignee = User::factory()->create();
        $lead = Lead::factory()->recycle($assignee->location)->create();
        $task = Task::factory()->for($lead)->for($assignee)->create([
            'due_at' => '2026-10-15 09:00:00',
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        $task->refresh()->update([
            'due_at' => '2026-10-15 11:00:00',
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        Notification::assertSentToTimes($assignee, TaskDueNotification::class, 2);
    }

    public function test_due_reminders_run_every_fifteen_minutes(): void
    {
        $event = collect(Schedule::events())
            ->first(fn (object $event): bool => str_contains((string) $event->command, 'tasks:send-due-reminders'));

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
