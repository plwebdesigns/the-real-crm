<?php

namespace App\Filament\Widgets;

use App\Enums\SaleType;
use App\Filament\Widgets\Concerns\AppliesAnalyticsLocationFilter;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LeadTypePerformanceTable extends TableWidget
{
    use AppliesAnalyticsLocationFilter;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 5;

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
            ->heading('Lead types')
            ->records(function (?string $sortColumn, ?string $sortDirection): array {
                $rows = $this->leadTypeRows();
                $column = $sortColumn ?: 'closed_volume';
                $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

                usort($rows, function (array $left, array $right) use ($column, $direction): int {
                    $result = $column === 'name'
                        ? strcasecmp((string) $left['name'], (string) $right['name'])
                        : ((float) $left[$column]) <=> ((float) $right[$column]);

                    return $direction === 'desc' ? -$result : $result;
                });

                return $rows;
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Type')
                    ->sortable(),
                TextColumn::make('closed_sales_count')
                    ->label('Closed sales')
                    ->numeric()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('closed_volume')
                    ->label('Closed volume')
                    ->money('USD')
                    ->color('success')
                    ->sortable(),
                TextColumn::make('closed_percent')
                    ->label('Closed %')
                    ->color('success')
                    ->sortable()
                    ->getStateUsing(fn (array $record): string => $this->formatClosedPercent($record['closed_percent'])),
            ])
            ->defaultSort('closed_volume', 'desc')
            ->paginated(false);
    }

    /**
     * @return list<array{__key: string, name: string, closed_sales_count: int, closed_volume: string, closed_percent: float}>
     */
    private function leadTypeRows(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        [$start, $end] = $this->selectedPeriod()->range();
        $locationId = $this->selectedLocationId();
        $sales = Sale::query()
            ->visibleTo($user)
            ->inLocation($locationId)
            ->closedBetween($start, $end)
            ->toBase()
            ->select('sale_type')
            ->selectRaw('count(*) as closed_sales_count')
            ->selectRaw('coalesce(sum(price), 0) as closed_volume')
            ->groupBy('sale_type')
            ->get()
            ->keyBy('sale_type');
        $leads = Lead::query()
            ->visibleTo($user)
            ->inLocation($locationId)
            ->createdBetween($start, $end)
            ->toBase()
            ->select('type')
            ->selectRaw('count(*) as leads_count')
            ->selectRaw('coalesce(sum(case when '.Lead::closedSaleExistsSql().' then 1 else 0 end), 0) as closed_leads_count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return array_map(function (SaleType $type) use ($sales, $leads): array {
            $sale = $sales->get($type->value);
            $lead = $leads->get($type->value);
            $leadsCount = (int) ($lead->leads_count ?? 0);
            $closedLeads = (int) ($lead->closed_leads_count ?? 0);

            return [
                '__key' => $type->value,
                'name' => (string) $type->getLabel(),
                'closed_sales_count' => (int) ($sale->closed_sales_count ?? 0),
                'closed_volume' => number_format((float) ($sale->closed_volume ?? 0), 2, '.', ''),
                'closed_percent' => $leadsCount === 0 ? 0.0 : ($closedLeads / $leadsCount) * 100,
            ];
        }, SaleType::cases());
    }
}
