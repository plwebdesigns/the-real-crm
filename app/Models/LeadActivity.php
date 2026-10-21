<?php

namespace App\Models;

use App\Enums\LeadActivityType;
use Carbon\CarbonInterface;
use Database\Factories\LeadActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id',
    'user_id',
    'type',
    'body',
    'happened_at',
])]
class LeadActivity extends Model
{
    /** @use HasFactory<LeadActivityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeadActivityType::class,
            'happened_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calls, emails, texts, and meetings. Notes are not outreach.
     *
     * @param  Builder<LeadActivity>  $query
     * @return Builder<LeadActivity>
     */
    #[Scope]
    protected function outreach(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('type'), [
            LeadActivityType::Call,
            LeadActivityType::Email,
            LeadActivityType::Text,
            LeadActivityType::Meeting,
        ]);
    }

    /**
     * @param  Builder<LeadActivity>  $query
     * @return Builder<LeadActivity>
     */
    #[Scope]
    protected function happenedBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query
            ->whereDate($query->qualifyColumn('happened_at'), '>=', $start->toDateString())
            ->whereDate($query->qualifyColumn('happened_at'), '<=', $end->toDateString());
    }
}
