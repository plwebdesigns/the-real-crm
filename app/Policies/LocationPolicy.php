<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsSuperAdministrators;

class LocationPolicy
{
    use AllowsSuperAdministrators;
}
