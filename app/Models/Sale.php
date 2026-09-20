<?php

namespace App\Models;

use App\Enums\BrokerageFeeType;
use App\Enums\SaleType;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'lead_id',
    'sale_status_id',
    'sale_type',
    'street_address',
    'city',
    'state',
    'postal_code',
    'price',
    'commission_percentage',
    'gross_commission',
    'brokerage_fee',
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
            'sale_type' => SaleType::class,
            'price' => 'decimal:2',
            'commission_percentage' => 'decimal:1',
            'gross_commission' => 'decimal:2',
            'brokerage_fee' => 'decimal:2',
            'closed_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Sale $sale): void {
            $sale->gross_commission = self::grossCommissionFor(
                $sale->price,
                $sale->commission_percentage,
            );
            $sale->brokerage_fee = self::brokerageFeeFor($sale->gross_commission);
        });

        static::saved(function (Sale $sale): void {
            if (! $sale->wasChanged(['price', 'commission_percentage', 'brokerage_fee'])) {
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

    public static function brokerageFeeFor(mixed $grossCommission): string
    {
        $amount = (float) config('app.brokerage_fee');
        $type = BrokerageFeeType::from((string) config('app.brokerage_fee_type'));
        $gross = (float) $grossCommission;

        $fee = match ($type) {
            BrokerageFeeType::Percent => $gross * $amount / 100,
            BrokerageFeeType::Fixed => $amount,
        };

        return number_format(round(min(max($fee, 0), $gross), 2), 2, '.', '');
    }

    public static function remainingCommissionFor(mixed $grossCommission, mixed $brokerageFee): string
    {
        return number_format(round(max(0, ((float) $grossCommission) - ((float) $brokerageFee)), 2), 2, '.', '');
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

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    #[Scope]
    protected function assignedTo(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'agents',
            fn (Builder $agents): Builder => $agents->whereKey($user->id),
        );
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    #[Scope]
    protected function closed(Builder $query): Builder
    {
        return $query->whereHas(
            'status',
            fn (Builder $status): Builder => $status->where('slug', 'closed'),
        );
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    #[Scope]
    protected function closedInYear(Builder $query, int $year): Builder
    {
        return $query
            ->closed()
            ->whereYear('closed_at', $year);
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->whereHas(
            'status',
            fn (Builder $status): Builder => $status->where('slug', 'pending'),
        );
    }
}
