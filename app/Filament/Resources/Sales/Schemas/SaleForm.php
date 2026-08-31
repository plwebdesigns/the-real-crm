<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Models\Lead;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('lead_id')
                    ->label('Lead')
                    ->relationship('lead', 'last_name')
                    ->getOptionLabelFromRecordUsing(
                        fn (Lead $record): string => $record->full_name,
                    )
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->preload()
                    ->required(),
                Select::make('sale_status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('street_address')
                    ->required()
                    ->maxLength(255),
                TextInput::make('city')
                    ->required()
                    ->maxLength(255),
                TextInput::make('state')
                    ->required()
                    ->length(2)
                    ->maxLength(2),
                TextInput::make('postal_code')
                    ->required()
                    ->maxLength(10),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                DateTimePicker::make('closed_at'),
                Repeater::make('agentAssignments')
                    ->label('Agents')
                    ->relationship()
                    ->schema([
                        Select::make('user_id')
                            ->label('Agent')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('commission_percent')
                            ->label('Commission %')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                    ])
                    ->minItems(1)
                    ->defaultItems(1)
                    ->columns(2)
                    ->required(),
            ]);
    }
}
