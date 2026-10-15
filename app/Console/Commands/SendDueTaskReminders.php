<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDueNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasks:send-due-reminders')]
#[Description('Email assignees when a follow-up task is due')]
class SendDueTaskReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Task::query()
            ->dueForReminder()
            ->with(['lead', 'user'])
            ->lazyById()
            ->each(function (Task $task): void {
                $assignee = $task->user;

                if (! $assignee instanceof User) {
                    return;
                }

                $assignee->notify(new TaskDueNotification($task));

                $task->forceFill([
                    'reminder_sent_at' => now(),
                ])->save();
            });

        return self::SUCCESS;
    }
}
