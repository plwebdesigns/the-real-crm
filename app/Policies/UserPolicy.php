<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, User $model): bool
    {
        return $this->administers($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, User $model): bool
    {
        return $this->administers($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->administers($user, $model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_admin;
    }

    private function administers(User $user, User $model): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        if (! $user->is_admin || $model->is_super_admin) {
            return false;
        }

        return $user->canAccessLocation($model->location_id);
    }
}
