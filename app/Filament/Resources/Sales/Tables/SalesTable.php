<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Enums\SaleType;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lead.last_name')
                    ->label('Lead')
                    ->formatStateUsing(
                        fn (?string $state, Sale $record): ?string => $record->lead?->full_name,
                    )
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('status.name')
                    ->label('Status')
                    ->sortable(),
                TextColumn::make('sale_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('street_address')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state')
                    ->searchable(),
                TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('commission_percentage')
                    ->label('Commission %')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('gross_commission')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('brokerage_fee')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('agents.name')
                    ->label('Agents')
                    ->badge()
                    ->separator(','),
                TextColumn::make('closed_at')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('sale_status_id')
                    ->label('Status')
                    ->relationship('status', 'name'),
                SelectFilter::make('sale_type')
                    ->options(SaleType::class),
                SelectFilter::make('agents')
                    ->label('Agent')
                    ->relationship(
                        'agents',
                        'name',
                        function (Builder $query): Builder {
                            $user = auth()->user();

                            return $user instanceof User
                                ? $query->visibleTo($user)
                                : $query->whereRaw('0 = 1');
                        },
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('source')
                    ->label('Source')
                    ->relationship('lead.source', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
