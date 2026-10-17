<?php

namespace App\Models;

use App\Enums\TaskType;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'lead_id',
    'sale_id',
    'user_id',
    'type',
    'due_at',
    'notes',
    'completed_at',
    'reminder_sent_at',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            if ($task->exists && ($task->isDirty('due_at') || $task->isDirty('user_id'))) {
                $task->reminder_sent_at = null;
            }

            $task->assertRelationshipsAreValid();
        });
    }

    public function isOpen(): bool
    {
        return $this->completed_at === null;
    }

    public function complete(User $completedBy): ?LeadActivity
    {
        return DB::transaction(function () use ($completedBy): ?LeadActivity {
            $task = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if (! $task instanceof self || ! $task->isOpen()) {
                return null;
            }

            $task->completed_at = now();
            $task->save();

            $activity = $task->lead()->firstOrFail()->activities()->create([
                'user_id' => $completedBy->id,
                'type' => $task->type->toActivityType(),
                'body' => $task->completedActivityBody(),
                'happened_at' => now(),
            ]);

            $this->refresh();

            return $activity;
        });
    }

    public function reopen(): void
    {
        if ($this->isOpen()) {
            return;
        }

        $this->completed_at = null;
        $this->save();
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('completed_at'));
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    #[Scope]
    protected function completed(Builder $query): Builder
    {
        return $query->whereNotNull($query->qualifyColumn('completed_at'));
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    #[Scope]
    protected function assignedTo(Builder $query, User $user): Builder
    {
        return $query->whereBelongsTo($user);
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        return $query->whereHas(
            'lead',
            fn (Builder $lead): Builder => $lead->where('location_id', $user->location_id),
        );
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    #[Scope]
    protected function dueForReminder(Builder $query): Builder
    {
        return $query
            ->open()
            ->whereNotNull($query->qualifyColumn('user_id'))
            ->where($query->qualifyColumn('due_at'), '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull($query->qualifyColumn('reminder_sent_at'))
                    ->orWhereColumn('reminder_sent_at', '<', 'due_at');
            });
    }

    private function assertRelationshipsAreValid(): void
    {
        if ($this->sale_id !== null) {
            $saleLeadId = Sale::query()->whereKey($this->sale_id)->value('lead_id');

            if ($saleLeadId === null || (int) $saleLeadId !== (int) $this->lead_id) {
                throw ValidationException::withMessages([
                    'sale_id' => 'The sale must belong to the selected lead.',
                ]);
            }
        }

        if ($this->user_id === null) {
            if ($this->exists) {
                return;
            }

            throw ValidationException::withMessages([
                'user_id' => 'An assignee is required.',
            ]);
        }

        $leadLocationId = Lead::query()->whereKey($this->lead_id)->value('location_id');
        $assignee = User::query()->find($this->user_id);

        if (! $assignee instanceof User || ! $assignee->canAccessLocation(
            $leadLocationId === null ? null : (int) $leadLocationId,
        )) {
            throw ValidationException::withMessages([
                'user_id' => "The assignee must be able to access the lead's office.",
            ]);
        }
    }

    private function completedActivityBody(): string
    {
        $notes = trim((string) $this->notes);

        if ($notes !== '') {
            return $notes;
        }

        return 'Follow-up '.strtolower((string) $this->type->getLabel()).' completed.';
    }
}
