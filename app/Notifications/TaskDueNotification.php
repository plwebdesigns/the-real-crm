<?php

namespace App\Notifications;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
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
        $channels = ['database'];

        if ($notifiable instanceof User && $notifiable->receives_task_reminder_emails) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->actions([
                Action::make('view')
                    ->label('View task')
                    ->url(TaskResource::getUrl('edit', ['record' => $this->task], panel: 'app')),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->markdown('mail.task-due', [
                'type' => $this->typeLabel(),
                'leadName' => $this->leadName(),
                'dueAt' => $this->task->due_at->timezone(config('app.timezone'))->format('M j, Y g:i A'),
                'notes' => $this->task->notes,
                'url' => TaskResource::getUrl('edit', ['record' => $this->task], panel: 'app'),
            ]);
    }

    private function title(): string
    {
        return "Follow-up {$this->typeLabel()} due for {$this->leadName()}";
    }

    private function body(): string
    {
        $notes = trim((string) $this->task->notes);

        if ($notes !== '') {
            return $notes;
        }

        return "{$this->leadName()} has a follow-up {$this->typeLabel()} due.";
    }

    private function typeLabel(): string
    {
        return strtolower((string) $this->task->type->getLabel());
    }

    private function leadName(): string
    {
        return $this->task->lead?->full_name ?? 'a lead';
    }
}
