<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Models\Sale;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                TextColumn::make('agents.name')
                    ->label('Agents')
                    ->badge()
                    ->separator(','),
                TextColumn::make('closed_at')
                    ->dateTime()
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
