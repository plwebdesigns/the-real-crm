<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\AllowsLocationMembers;

class SalePolicy
{
    use AllowsLocationMembers;

    public function view(User $user, Lead|Sale $model): bool
    {
        return $model instanceof Sale && $this->canAccess($user, $model);
    }

    public function update(User $user, Lead|Sale $model): bool
    {
        return $model instanceof Sale && $this->canAccess($user, $model);
    }

    public function delete(User $user, Lead|Sale $model): bool
    {
        return $model instanceof Sale && $this->canAccess($user, $model);
    }

    private function canAccess(User $user, Sale $sale): bool
    {
        if (! $user->canAccessLocation($sale->location_id)) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return $sale->agents()->whereKey($user->id)->exists();
    }
}
