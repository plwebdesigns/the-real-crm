<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdministrators;

class LeadStatusPolicy
{
    use AllowsAdministrators;
}
