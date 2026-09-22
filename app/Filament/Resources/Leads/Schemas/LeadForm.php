<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\SaleType;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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
                Select::make('type')
                    ->options(SaleType::class)
                    ->required(),
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
                Select::make('location_id')
                    ->label('Location')
                    ->relationship('office', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->visible(fn (): bool => auth()->user()?->is_super_admin ?? false),
                Select::make('agents')
                    ->relationship(
                        'agents',
                        'name',
                        function (Builder $query, Get $get): Builder {
                            $user = auth()->user();

                            if (! $user instanceof User) {
                                return $query->whereRaw('0 = 1');
                            }

                            $locationId = $user->is_super_admin
                                ? $get('location_id')
                                : $user->location_id;

                            return $query->atLocation(
                                is_numeric($locationId) ? (int) $locationId : null,
                            );
                        },
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->default(fn (): array => array_filter([auth()->id()])),
                TextInput::make('location')
                    ->label('Property location')
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
