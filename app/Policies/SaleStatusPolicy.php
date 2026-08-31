<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdministrators;

class SaleStatusPolicy
{
    use AllowsAdministrators;
}
