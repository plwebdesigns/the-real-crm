<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
                Toggle::make('is_super_admin')
                    ->label('Super administrator')
                    ->visible(fn (): bool => auth()->user()?->is_super_admin ?? false)
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        if ($state) {
                            $set('is_admin', true);
                            $set('location_id', null);
                        }
                    }),
                Select::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->is_super_admin ?? false)
                    ->required(fn (Get $get): bool => ! $get('is_super_admin'))
                    ->disabled(fn (Get $get): bool => (bool) $get('is_super_admin')),
                Toggle::make('is_admin')
                    ->label('Administrator')
                    ->disabled(fn (Get $get): bool => (bool) $get('is_super_admin'))
                    ->dehydrated(),
            ]);
    }
}
