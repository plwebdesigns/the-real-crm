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
            'closed_at' => 'datetime',
        ];
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
            ->withPivot('commission_percent')
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
