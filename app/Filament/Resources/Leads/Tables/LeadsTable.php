<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\SaleType;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status.name')
                    ->label('Status')
                    ->sortable(),
                TextColumn::make('source.name')
                    ->label('Source')
                    ->sortable(),
                TextColumn::make('agents.name')
                    ->label('Agents')
                    ->badge()
                    ->separator(','),
                TextColumn::make('location')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('property_type')
                    ->toggleable(),
                TextColumn::make('price_range')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(SaleType::class),
                SelectFilter::make('lead_status_id')
                    ->label('Status')
                    ->relationship('status', 'name'),
                SelectFilter::make('lead_source_id')
                    ->label('Source')
                    ->relationship('source', 'name'),
                SelectFilter::make('agents')
                    ->label('Agent')
                    ->relationship('agents', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('createRelatedLead')
                    ->label('Create related lead')
                    ->url(fn (Lead $record): string => LeadResource::getUrl('create', [
                        'related' => $record->id,
                    ], isAbsolute: false)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
