<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdministrators;

class UserPolicy
{
    use AllowsAdministrators;
}
