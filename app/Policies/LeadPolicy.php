<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAuthenticatedUsers;

class LeadPolicy
{
    use AllowsAuthenticatedUsers;
}
