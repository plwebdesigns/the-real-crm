<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TasksTable
{
    public static function configure(Table $table, bool $showLead = true, bool $showSale = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['lead', 'sale', 'user']))
            ->columns([
                TextColumn::make('due_at')
                    ->label('Due')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (Task $record): ?string => $record->isOpen() && $record->due_at->lte(now()) ? 'danger' : null),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('lead.last_name')
                    ->label('Lead')
                    ->formatStateUsing(
                        fn (?string $state, Task $record): string => $record->lead?->full_name ?? '',
                    )
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas(
                            'lead',
                            fn (Builder $lead): Builder => $lead->where(function (Builder $lead) use ($search): void {
                                $lead->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            }),
                        );
                    })
                    ->visible($showLead),
                TextColumn::make('sale.street_address')
                    ->label('Sale')
                    ->placeholder('None')
                    ->visible($showSale),
                TextColumn::make('user.name')
                    ->label('Assignee'),
                TextColumn::make('notes')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('due_at')
            ->filters([
                SelectFilter::make('type')
                    ->options(TaskType::class),
            ])
            ->recordActions([
                self::completeAction(),
                self::reopenAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function completeAction(): Action
    {
        return Action::make('complete')
            ->icon(Heroicon::OutlinedCheck)
            ->requiresConfirmation()
            ->modalHeading('Complete follow-up')
            ->modalDescription('This marks the task done and adds a matching call, email, or text to the lead.')
            ->successNotificationTitle('Follow-up completed and added to the lead.')
            ->visible(fn (Task $record): bool => $record->isOpen())
            ->authorize(fn (Task $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Task $record, Action $action): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                $record->complete($user);
                $action->success();
            });
    }

    public static function reopenAction(): Action
    {
        return Action::make('reopen')
            ->successNotificationTitle('Follow-up reopened.')
            ->visible(fn (Task $record): bool => ! $record->isOpen())
            ->authorize(fn (Task $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Task $record, Action $action): void {
                $record->reopen();
                $action->success();
            });
    }
}
