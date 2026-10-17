<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskType;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\User;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaskForm
{
    public static function configure(Schema $schema, ?Model $owner = null): Schema
    {
        return $schema
            ->components([
                Select::make('lead_id')
                    ->label('Lead')
                    ->relationship(
                        'lead',
                        'last_name',
                        function (Builder $query): Builder {
                            $user = auth()->user();

                            if (! $user instanceof User) {
                                return $query->whereRaw('0 = 1');
                            }

                            return $query->visibleTo($user);
                        },
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Lead $record): string => $record->nameWithType(),
                    )
                    ->searchable(['first_name', 'last_name'])
                    ->preload()
                    ->required()
                    ->live()
                    ->visible(fn (): bool => ! $owner instanceof Lead && ! $owner instanceof Sale)
                    ->afterStateUpdated(fn (Set $set): mixed => $set('sale_id', null))
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $user = auth()->user();
                            $lead = Lead::query()->find($value);

                            if (! $user instanceof User || ! $lead instanceof Lead || ! $user->can('view', $lead)) {
                                $fail('The selected lead is invalid.');
                            }
                        },
                    ]),
                Select::make('sale_id')
                    ->label('Sale')
                    ->options(function (Get $get) use ($owner): array {
                        $leadId = self::leadId($owner, $get);

                        if ($leadId === null) {
                            return [];
                        }

                        return Sale::query()
                            ->where('lead_id', $leadId)
                            ->orderBy('street_address')
                            ->pluck('street_address', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => ! $owner instanceof Sale)
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $owner): void {
                            if (blank($value)) {
                                return;
                            }

                            $belongsToLead = Sale::query()
                                ->whereKey($value)
                                ->where('lead_id', self::leadId($owner, $get))
                                ->exists();

                            if (! $belongsToLead) {
                                $fail('The sale must belong to the selected lead.');
                            }
                        },
                    ]),
                Select::make('type')
                    ->options(TaskType::class)
                    ->required(),
                DateTimePicker::make('due_at')
                    ->label('Due')
                    ->required(),
                Textarea::make('notes')
                    ->rows(4),
                Select::make('user_id')
                    ->label('Assignee')
                    ->options(function (Get $get) use ($owner): array {
                        $leadId = self::leadId($owner, $get);
                        $lead = $leadId === null ? null : Lead::query()->find($leadId);

                        if (! $lead instanceof Lead) {
                            return [];
                        }

                        return User::query()
                            ->where(function (Builder $query) use ($lead): void {
                                $query->where('location_id', $lead->location_id)
                                    ->orWhere('is_super_admin', true);
                            })
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn (): mixed => auth()->id())
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $owner): void {
                            $lead = Lead::query()->find(self::leadId($owner, $get));
                            $assignee = User::query()->find($value);

                            if (! $lead instanceof Lead || ! $assignee instanceof User || ! $assignee->canAccessLocation($lead->location_id)) {
                                $fail("The assignee must be able to access the lead's office.");
                            }
                        },
                    ]),
            ]);
    }

    private static function leadId(?Model $owner, Get $get): ?int
    {
        if ($owner instanceof Lead) {
            return $owner->id;
        }

        if ($owner instanceof Sale) {
            return $owner->lead_id;
        }

        $leadId = $get('lead_id');

        return is_numeric($leadId) ? (int) $leadId : null;
    }
}
