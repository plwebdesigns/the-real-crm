<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $this->canAccessLead($user, $task);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        return $this->canAccessLead($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->canAccessLead($user, $task);
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }

    private function canAccessLead(User $user, Task $task): bool
    {
        return $user->canAccessLocation($task->lead?->location_id);
    }
}
