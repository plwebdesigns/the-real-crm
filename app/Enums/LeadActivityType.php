<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum LeadActivityType: string implements HasLabel
{
    case Call = 'call';
    case Email = 'email';
    case Text = 'text';
    case Meeting = 'meeting';
    case Note = 'note';

    public function getLabel(): string|Htmlable|null
    {
        return $this->name;
    }
}
