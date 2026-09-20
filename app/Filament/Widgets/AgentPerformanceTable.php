<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\SaleUser;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class AgentPerformanceTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_admin;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Agents')
            ->query($this->agentsQuery())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (User $record): string => $this->salesIndexUrl($record)),
                TextColumn::make('closed_sales_count')
                    ->label('Closed sales')
                    ->numeric()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('closed_volume')
                    ->label('Closed volume')
                    ->money('USD')
                    ->default(0)
                    ->color('success')
                    ->sortable(),
                TextColumn::make('brokerage_fee')
                    ->label('Brokerage fees')
                    ->money('USD')
                    ->default(0)
                    ->color('success')
                    ->sortable(),
                TextColumn::make('net_commission')
                    ->label('Net commission')
                    ->money('USD')
                    ->default(0)
                    ->color('success')
                    ->sortable(),
                TextColumn::make('pending_sales_count')
                    ->label('Pending sales')
                    ->numeric()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('assigned_leads_count')
                    ->label('Assigned leads')
                    ->numeric()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('working_leads_count')
                    ->label('Working leads')
                    ->numeric()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('closed_percent')
                    ->label('Closed %')
                    ->color('success')
                    ->getStateUsing(function (User $record): string {
                        $assignedCount = (int) $record->assigned_leads_count;
                        $closedCount = (int) $record->closed_leads_count;
                        $closedPercent = $assignedCount === 0
                            ? 0.0
                            : ($closedCount / $assignedCount) * 100;

                        return Number::percentage($closedPercent, precision: 0) ?: '0%';
                    }),
            ])
            ->defaultSort('net_commission', 'desc');
    }

    /**
     * @return Builder<User>
     */
    private function agentsQuery(): Builder
    {
        $year = now()->year;

        return User::query()
            ->withCount([
                'sales as closed_sales_count' => fn (Builder $sales): Builder => $sales->closedInYear($year),
                'sales as pending_sales_count' => fn (Builder $sales): Builder => $sales->pending(),
                'leads as assigned_leads_count',
                'leads as working_leads_count' => fn (Builder $leads): Builder => $leads->working(),
                'leads as closed_leads_count' => fn (Builder $leads): Builder => $leads->closed(),
            ])
            ->withSum(
                [
                    'sales as closed_volume' => fn (Builder $sales): Builder => $sales->closedInYear($year),
                ],
                'price',
            )
            ->withSum(
                [
                    'sales as brokerage_fee' => fn (Builder $sales): Builder => $sales->closedInYear($year),
                ],
                'brokerage_fee',
            )
            ->addSelect([
                'net_commission' => SaleUser::query()
                    ->selectRaw('coalesce(sum(sale_user.net_commission), 0)')
                    ->whereColumn('sale_user.user_id', 'users.id')
                    ->whereHas(
                        'sale',
                        fn (Builder $sales): Builder => $sales->closedInYear($year),
                    ),
            ])
            ->withCasts([
                'closed_volume' => 'decimal:2',
                'brokerage_fee' => 'decimal:2',
                'net_commission' => 'decimal:2',
            ]);
    }

    private function salesIndexUrl(User $user): string
    {
        return SaleResource::getUrl('index', [
            'tab' => 'closed',
            'filters' => [
                'agents' => [
                    'value' => $user->id,
                ],
            ],
        ], isAbsolute: false);
    }
}
