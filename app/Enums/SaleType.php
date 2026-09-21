<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum SaleType: string implements HasLabel
{
    case Seller = 'seller';
    case Buyer = 'buyer';
    case Rental = 'rental';
    case Other = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return $this->name;
    }

    public function relatedType(): self
    {
        return match ($this) {
            self::Seller => self::Buyer,
            self::Buyer => self::Seller,
            self::Rental, self::Other => $this,
        };
    }
}
