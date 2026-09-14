<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AssignedLeadsTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading('My leads')
            ->query($this->assignedLeadsQuery())
            ->columns([
                TextColumn::make('last_name')
                    ->label('Name')
                    ->formatStateUsing(
                        fn (?string $state, Lead $record): string => $record->full_name,
                    )
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('status.name')
                    ->label('Status')
                    ->sortable(),
                TextColumn::make('source.name')
                    ->label('Source')
                    ->sortable(),
                TextColumn::make('location')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->url(fn (Lead $record): string => LeadResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    /**
     * @return Builder<Lead>
     */
    private function assignedLeadsQuery(): Builder
    {
        $user = auth()->user();

        $query = Lead::query()
            ->with(['status', 'source'])
            ->latest('created_at')
            ->orderByDesc('id');

        if (! $user instanceof User) {
            return $query->whereRaw('0 = 1');
        }

        return $query->assignedTo($user);
    }
}
