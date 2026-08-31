<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAuthenticatedUsers;

class SalePolicy
{
    use AllowsAuthenticatedUsers;
}
