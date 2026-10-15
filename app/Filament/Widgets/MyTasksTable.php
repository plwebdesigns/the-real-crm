<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class MyTasksTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading('My tasks')
            ->query($this->openTasksQuery())
            ->columns([
                TextColumn::make('due_at')
                    ->label('Due')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (Task $record): ?string => $record->due_at->lte(now()) ? 'danger' : null),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('lead.last_name')
                    ->label('Lead')
                    ->formatStateUsing(
                        fn (?string $state, Task $record): string => $record->lead?->full_name ?? '',
                    ),
                TextColumn::make('notes')
                    ->limit(80)
                    ->wrap(),
            ])
            ->defaultSort('due_at')
            ->recordActions([
                EditAction::make()
                    ->url(fn (Task $record): string => TaskResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    /**
     * @return Builder<Task>
     */
    private function openTasksQuery(): Builder
    {
        $user = auth()->user();

        $query = Task::query()->with(['lead', 'user']);

        if (! $user instanceof User) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->visibleTo($user)
            ->assignedTo($user)
            ->open();
    }
}
