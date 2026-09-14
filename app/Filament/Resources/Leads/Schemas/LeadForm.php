<?php

namespace App\Filament\Resources\Leads\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Select::make('lead_status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('lead_source_id')
                    ->label('Source')
                    ->relationship('source', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('agents')
                    ->relationship('agents', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->default(fn (): array => array_filter([auth()->id()])),
                TextInput::make('location')
                    ->maxLength(255),
                TextInput::make('property_type')
                    ->maxLength(255),
                TextInput::make('price_range')
                    ->maxLength(255),
                TextInput::make('bedrooms')
                    ->integer()
                    ->minValue(0),
                TextInput::make('bathrooms')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.5),
                Grid::make(['default' => 2])
                    ->schema([
                        Toggle::make('garage'),
                        Toggle::make('pool'),
                    ]),
                Textarea::make('notes')
                    ->rows(4),
            ]);
    }
}
