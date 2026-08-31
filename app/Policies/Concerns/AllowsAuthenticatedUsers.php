<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AllowsAuthenticatedUsers
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, mixed $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, mixed $model): bool
    {
        return true;
    }

    public function delete(User $user, mixed $model): bool
    {
        return true;
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }
}
