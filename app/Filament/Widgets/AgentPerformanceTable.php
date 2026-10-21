<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Widgets\Concerns\AppliesAnalyticsLocationFilter;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SaleUser;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentPerformanceTable extends TableWidget
{
    use AppliesAnalyticsLocationFilter;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 6;

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
                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('All locations')
                    ->sortable()
                    ->toggleable()
                    ->visible(fn (): bool => auth()->user()?->is_super_admin ?? false),
                TextColumn::make('closed_sales_count')
                    ->label('Closed sales')
                    ->numeric()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('closed_volume')
                    ->label('Production volume')
                    ->money('USD')
                    ->default(0)
                    ->color('success')
                    ->sortable(),
                TextColumn::make('credited_volume')
                    ->label('Credited volume')
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
                    ->sortable()
                    ->getStateUsing(fn (User $record): string => $this->formatClosedPercent($record->closed_percent)),
                TextColumn::make('activities_count')
                    ->label('Activities')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('overdue_tasks_count')
                    ->label('Overdue tasks')
                    ->numeric()
                    ->color('danger')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
            ])
            ->defaultSort('net_commission', 'desc');
    }

    /**
     * @return Builder<User>
     */
    private function agentsQuery(): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return User::query()->whereRaw('0 = 1');
        }

        [$start, $end] = $this->selectedPeriod()->range();
        $locationId = $this->selectedLocationId();

        return User::query()
            ->visibleTo($user)
            ->inLocation($locationId)
            ->with('location')
            ->withCount([
                'sales as closed_sales_count' => fn (Builder $sales): Builder => $sales->closedBetween($start, $end)->visibleTo($user)->inLocation($locationId),
                'sales as pending_sales_count' => fn (Builder $sales): Builder => $sales->pending()->visibleTo($user)->inLocation($locationId),
                'leads as assigned_leads_count' => fn (Builder $leads): Builder => $leads->visibleTo($user)->inLocation($locationId)->createdBetween($start, $end),
                'leads as working_leads_count' => fn (Builder $leads): Builder => $leads->working()->visibleTo($user)->inLocation($locationId)->createdBetween($start, $end),
                'leadActivities as activities_count' => fn (Builder $activities): Builder => $activities
                    ->outreach()
                    ->happenedBetween($start, $end)
                    ->whereHas(
                        'lead',
                        fn (Builder $lead): Builder => $lead->visibleTo($user)->inLocation($locationId),
                    ),
                'tasks as overdue_tasks_count' => fn (Builder $tasks): Builder => $tasks
                    ->overdue()
                    ->whereHas(
                        'lead',
                        fn (Builder $lead): Builder => $lead->visibleTo($user)->inLocation($locationId),
                    ),
            ])
            ->withSum(
                [
                    'sales as closed_volume' => fn (Builder $sales): Builder => $sales->closedBetween($start, $end)->visibleTo($user)->inLocation($locationId),
                ],
                'price',
            )
            ->withSum(
                [
                    'sales as brokerage_fee' => fn (Builder $sales): Builder => $sales->closedBetween($start, $end)->visibleTo($user)->inLocation($locationId),
                ],
                'brokerage_fee',
            )
            ->addSelect([
                'net_commission' => SaleUser::query()
                    ->selectRaw('coalesce(sum(sale_user.net_commission), 0)')
                    ->whereColumn('sale_user.user_id', 'users.id')
                    ->whereHas(
                        'sale',
                        fn (Builder $sales): Builder => $sales->closedBetween($start, $end)->visibleTo($user)->inLocation($locationId),
                    ),
                'credited_volume' => $this->creditedVolumeQuery($user, $locationId, $start, $end),
            ])
            ->selectSub(
                $this->closedPercentQuery($user, $locationId, $start, $end),
                'closed_percent',
            )
            ->withCasts([
                'closed_volume' => 'decimal:2',
                'credited_volume' => 'decimal:2',
                'brokerage_fee' => 'decimal:2',
                'net_commission' => 'decimal:2',
            ]);
    }

    /**
     * @return Builder<SaleUser>
     */
    private function creditedVolumeQuery(User $user, ?int $locationId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return SaleUser::query()
            ->selectRaw('coalesce(sum('.SaleUser::priceShareExpression().'), 0)')
            ->join('sales', 'sales.id', '=', 'sale_user.sale_id')
            ->whereColumn('sale_user.user_id', 'users.id')
            ->whereIn(
                'sale_user.sale_id',
                Sale::query()
                    ->closedBetween($start, $end)
                    ->visibleTo($user)
                    ->inLocation($locationId)
                    ->select('sales.id'),
            );
    }

    /**
     * @return Builder<Lead>
     */
    private function closedPercentQuery(User $user, ?int $locationId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return Lead::query()
            ->selectClosedPercent()
            ->join('lead_user', 'lead_user.lead_id', '=', 'leads.id')
            ->whereColumn('lead_user.user_id', 'users.id')
            ->createdBetween($start, $end)
            ->visibleTo($user)
            ->inLocation($locationId);
    }

    private function exportCsv(): StreamedResponse
    {
        $records = $this->getTableQueryForExport()->get();

        return $this->downloadCsv(
            'agent-performance.csv',
            [
                'Name',
                'Location',
                'Closed sales',
                'Production volume',
                'Credited volume',
                'Brokerage fees',
                'Net commission',
                'Pending sales',
                'Assigned leads',
                'Working leads',
                'Closed %',
                'Activities',
                'Overdue tasks',
            ],
            $records->map(fn (User $record): array => [
                $record->name,
                $record->location?->name ?? '',
                (int) $record->closed_sales_count,
                $record->closed_volume,
                $record->credited_volume,
                $record->brokerage_fee,
                $record->net_commission,
                (int) $record->pending_sales_count,
                (int) $record->assigned_leads_count,
                (int) $record->working_leads_count,
                $this->formatClosedPercent($record->closed_percent),
                (int) $record->activities_count,
                (int) $record->overdue_tasks_count,
            ]),
        );
    }

    private function salesIndexUrl(User $user): string
    {
        return SaleResource::getUrl('index', [
            'tab' => 'closed',
            'filters' => $this->filtersIncludingLocation([
                'agents' => [
                    'value' => $user->id,
                ],
            ]),
        ], isAbsolute: false);
    }
}
