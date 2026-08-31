<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdministrators;

class LeadSourcePolicy
{
    use AllowsAdministrators;
}
