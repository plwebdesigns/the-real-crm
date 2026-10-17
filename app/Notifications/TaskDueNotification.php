<?php

namespace App\Notifications;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class TaskDueNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Task $task) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = strtolower((string) $this->task->type->getLabel());
        $leadName = $this->task->lead?->full_name ?? 'a lead';

        return (new MailMessage)
            ->subject("Follow-up {$type} due for {$leadName}")
            ->markdown('mail.task-due', [
                'type' => $type,
                'leadName' => $leadName,
                'dueAt' => $this->task->due_at->timezone(config('app.timezone'))->format('M j, Y g:i A'),
                'notes' => $this->task->notes,
                'url' => TaskResource::getUrl('edit', ['record' => $this->task], panel: 'app'),
            ]);
    }
}
