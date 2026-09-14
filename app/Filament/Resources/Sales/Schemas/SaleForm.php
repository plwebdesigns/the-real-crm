<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\SaleType;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SaleUser;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                Select::make('sale_type')
                    ->options(SaleType::class)
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
                    ->prefix('$')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        self::updateCalculatedCommissions($set, $get);
                    }),
                TextInput::make('commission_percentage')
                    ->label('Commission %')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(6)
                    ->step(0.1)
                    ->rules(['decimal:0,1'])
                    ->suffix('%')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        self::updateCalculatedCommissions($set, $get);
                    }),
                TextInput::make('gross_commission')
                    ->label('Gross commission')
                    ->prefix('$')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
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
                            ->label('Split %')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(1)
                            ->maxValue(100)
                            ->step(1)
                            ->suffix('%')
                            ->default(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                $set(
                                    'net_commission',
                                    SaleUser::netCommissionFor(
                                        $get('../../gross_commission'),
                                        $get('commission_percent'),
                                    ),
                                );
                            }),
                        TextInput::make('net_commission')
                            ->label('Net commission')
                            ->prefix('$')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->minItems(1)
                    ->defaultItems(1)
                    ->columns(3)
                    ->required()
                    ->helperText('Agent splits must add up to 100%.')
                    ->rule(function (): Closure {
                        return function (string $attribute, mixed $value, Closure $fail): void {
                            $total = collect(is_array($value) ? $value : [])
                                ->sum(fn (mixed $item): int => (int) (is_array($item) ? ($item['commission_percent'] ?? 0) : 0));

                            if ($total !== 100) {
                                $fail("Agent commission splits must add up to 100%. Currently {$total}%.");
                            }
                        };
                    }),
            ]);
    }

    private static function updateCalculatedCommissions(Set $set, Get $get): void
    {
        $grossCommission = Sale::grossCommissionFor(
            $get('price'),
            $get('commission_percentage'),
        );

        $set('gross_commission', $grossCommission);

        foreach ($get('agentAssignments') ?? [] as $key => $assignment) {
            $set(
                "agentAssignments.{$key}.net_commission",
                SaleUser::netCommissionFor(
                    $grossCommission,
                    is_array($assignment) ? ($assignment['commission_percent'] ?? null) : null,
                ),
            );
        }
    }
}
