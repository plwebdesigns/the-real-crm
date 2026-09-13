<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SaleUser extends Pivot
{
    public $incrementing = true;

    protected $fillable = [
        'sale_id',
        'user_id',
        'commission_percent',
        'net_commission',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_percent' => 'integer',
            'net_commission' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SaleUser $assignment): void {
            $assignment->net_commission = self::netCommissionFor(
                $assignment->sale?->gross_commission,
                $assignment->commission_percent,
            );
        });
    }

    public static function netCommissionFor(mixed $grossCommission, mixed $percent): string
    {
        return number_format(round(((float) $grossCommission) * ((float) $percent) / 100, 2), 2, '.', '');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
