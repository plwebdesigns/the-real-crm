<?php

namespace App\Policies\Concerns;

use App\Models\Lead;
use App\Models\Sale;
use App\Models\User;

trait AllowsLocationMembers
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead|Sale $model): bool
    {
        return $user->canAccessLocation($model->location_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead|Sale $model): bool
    {
        return $user->canAccessLocation($model->location_id);
    }

    public function delete(User $user, Lead|Sale $model): bool
    {
        return $user->canAccessLocation($model->location_id);
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }
}
