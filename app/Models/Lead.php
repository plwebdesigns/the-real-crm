<?php

namespace App\Models;

use App\Enums\SaleType;
use Carbon\CarbonInterface;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'first_name',
    'last_name',
    'email',
    'phone',
    'type',
    'lead_status_id',
    'lead_source_id',
    'location_id',
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

    protected static function booted(): void
    {
        static::deleting(function (Lead $lead): void {
            $lead->documents()->get()->each(
                fn (LeadDocument $document): ?bool => $document->delete(),
            );
        });
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
     * @return BelongsTo<Location, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
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
     * @return HasMany<LeadActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<LeadDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(LeadDocument::class);
    }

    /**
     * @return HasOne<LeadActivity, $this>
     */
    public function latestActivity(): HasOne
    {
        return $this->hasOne(LeadActivity::class)->latestOfMany('happened_at');
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

    /**
     * Leads created during the analytics cohort window.
     *
     * Close rate uses this cohort: a lead created outside the period cannot
     * move the period's close rate, including a lead that closed earlier.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    #[Scope]
    protected function createdBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query
            ->whereDate($query->qualifyColumn('created_at'), '>=', $start->toDateString())
            ->whereDate($query->qualifyColumn('created_at'), '<=', $end->toDateString());
    }

    /**
     * Working leads with no activity in the last `$days` days.
     *
     * Leads created inside that window are excluded so a new lead is not
     * stale before it has had time to be touched.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    #[Scope]
    protected function staleWorking(Builder $query, int $days = 14): Builder
    {
        $cutoff = now()->subDays($days);

        return $query
            ->working()
            ->where($query->qualifyColumn('created_at'), '<=', $cutoff)
            ->whereDoesntHave(
                'activities',
                fn (Builder $activities): Builder => $activities->where('happened_at', '>=', $cutoff),
            );
    }

    /**
     * One scalar: the percent of leads in this query that have a closed sale.
     * Callers add the cohort and ownership constraints. Returns 0 when the
     * cohort is empty so the column can be sorted.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    #[Scope]
    protected function selectClosedPercent(Builder $query): Builder
    {
        return $query->selectRaw(
            'case when count(*) = 0 then 0 else round(100.0 * sum(case when '.self::closedSaleExistsSql().' then 1 else 0 end) / count(*), 0) end',
        );
    }

    public static function closedSaleExistsSql(): string
    {
        return 'exists (select 1 from sales inner join sale_statuses on sale_statuses.id = sales.sale_status_id where sales.lead_id = leads.id and sale_statuses.slug = \'closed\')';
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        return $query->where('location_id', $user->location_id);
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    #[Scope]
    protected function inLocation(Builder $query, ?int $locationId): Builder
    {
        if ($locationId === null) {
            return $query;
        }

        return $query->where($query->qualifyColumn('location_id'), $locationId);
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    #[Scope]
    protected function orderByLastTouched(Builder $query, string $direction = 'desc'): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $query->orderBy(
            LeadActivity::query()
                ->select('happened_at')
                ->whereColumn('lead_activities.lead_id', $query->qualifyColumn('id'))
                ->orderByDesc('happened_at')
                ->orderByDesc('id')
                ->limit(1),
            $direction,
        );
    }
}
