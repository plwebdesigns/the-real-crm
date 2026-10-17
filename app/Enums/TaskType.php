<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum TaskType: string implements HasLabel
{
    case Call = 'call';
    case Email = 'email';
    case Text = 'text';

    public function getLabel(): string|Htmlable|null
    {
        return $this->name;
    }

    public function toActivityType(): LeadActivityType
    {
        return LeadActivityType::from($this->value);
    }
}
