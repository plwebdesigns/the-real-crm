<?php

namespace App\Policies;

use App\Models\LeadActivity;
use App\Models\User;

class LeadActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeadActivity $leadActivity): bool
    {
        return $this->canAccessLead($user, $leadActivity);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LeadActivity $leadActivity): bool
    {
        return $this->canAccessLead($user, $leadActivity);
    }

    public function delete(User $user, LeadActivity $leadActivity): bool
    {
        return $this->canAccessLead($user, $leadActivity);
    }

    private function canAccessLead(User $user, LeadActivity $leadActivity): bool
    {
        return $user->canAccessLocation($leadActivity->lead?->location_id);
    }
}
