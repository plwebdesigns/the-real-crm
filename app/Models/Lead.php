<?php

namespace App\Models;

use App\Enums\SaleType;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'first_name',
    'last_name',
    'email',
    'phone',
    'type',
    'lead_status_id',
    'lead_source_id',
    'location',
    'property_type',
    'price_range',
    'bedrooms',
    'bathrooms',
    'garage',
    'pool',
    'notes',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SaleType::class,
            'bedrooms' => 'integer',
            'bathrooms' => 'decimal:1',
            'garage' => 'boolean',
            'pool' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function nameWithType(): string
    {
        return "{$this->full_name} ({$this->type->name})";
    }

    public function relatedType(): SaleType
    {
        return $this->type->relatedType();
    }

    /**
     * @return BelongsTo<LeadStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'lead_status_id');
    }

    /**
     * @return BelongsTo<LeadSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    /**
     * @return HasOne<Sale, $this>
     */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
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
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    #[Scope]
    protected function working(Builder $query): Builder
    {
        return $query->whereHas(
            'status',
            fn (Builder $status): Builder => $status->whereIn('slug', ['contacted', 'qualified']),
        );
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    #[Scope]
    protected function lost(Builder $query): Builder
    {
        return $query->whereHas(
            'status',
            fn (Builder $status): Builder => $status->where('slug', 'lost'),
        );
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    #[Scope]
    protected function closed(Builder $query): Builder
    {
        return $query->whereHas(
            'sale',
            fn (Builder $sale): Builder => $sale->closed(),
        );
    }
}
