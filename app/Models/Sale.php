<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'lead_id',
    'sale_status_id',
    'street_address',
    'city',
    'state',
    'postal_code',
    'price',
    'commission_percentage',
    'gross_commission',
    'closed_at',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission_percentage' => 'decimal:1',
            'gross_commission' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Sale $sale): void {
            $sale->gross_commission = self::grossCommissionFor(
                $sale->price,
                $sale->commission_percentage,
            );
        });

        static::saved(function (Sale $sale): void {
            if (! $sale->wasChanged(['price', 'commission_percentage'])) {
                return;
            }

            $sale->agentAssignments()->get()->each(
                fn (SaleUser $assignment): bool => $assignment->save(),
            );
        });
    }

    public static function grossCommissionFor(mixed $price, mixed $percentage): string
    {
        return number_format(round(((float) $price) * ((float) $percentage) / 100, 2), 2, '.', '');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<SaleStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(SaleStatus::class, 'sale_status_id');
    }

    /**
     * @return BelongsToMany<User, $this, SaleUser>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(SaleUser::class)
            ->withPivot(['commission_percent', 'net_commission'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<SaleUser, $this>
     */
    public function agentAssignments(): HasMany
    {
        return $this->hasMany(SaleUser::class);
    }
}
