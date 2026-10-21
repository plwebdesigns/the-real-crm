<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Widgets\Concerns\AppliesAnalyticsLocationFilter;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadSourcePerformanceTable extends TableWidget
{
    use AppliesAnalyticsLocationFilter;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 7;

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
            ->heading('Lead sources')
            ->query($this->sourcesQuery())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (LeadSource $record): string => $this->salesIndexUrl($record)),
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
                TextColumn::make('gross_commission')
                    ->label('Gross commission')
                    ->money('USD')
                    ->default(0)
                    ->color('success')
                    ->sortable(),
                TextColumn::make('pending_sales_count')
                    ->label('Pending sales')
                    ->numeric()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('leads_count')
                    ->label('Total leads')
                    ->numeric()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('closed_percent')
                    ->label('Closed %')
                    ->color('success')
                    ->sortable()
                    ->getStateUsing(fn (LeadSource $record): string => $this->formatClosedPercent($record->closed_percent)),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
            ])
            ->defaultSort('closed_volume', 'desc');
    }

    /**
     * @return Builder<LeadSource>
     */
    private function sourcesQuery(): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return LeadSource::query()->whereRaw('0 = 1');
        }

        [$start, $end] = $this->selectedPeriod()->range();
        $locationId = $this->selectedLocationId();

        return LeadSource::query()
            ->withCount([
                'sales as closed_sales_count' => fn (Builder $sales): Builder => $sales->closedBetween($start, $end)->visibleTo($user)->inLocation($locationId),
                'sales as pending_sales_count' => fn (Builder $sales): Builder => $sales->pending()->visibleTo($user)->inLocation($locationId),
                'leads as leads_count' => fn (Builder $leads): Builder => $leads->visibleTo($user)->inLocation($locationId)->createdBetween($start, $end),
            ])
            ->withSum(
                [
                    'sales as closed_volume' => fn (Builder $sales): Builder => $sales->closedBetween($start, $end)->visibleTo($user)->inLocation($locationId),
                ],
                'price',
            )
            ->withSum(
                [
                    'sales as gross_commission' => fn (Builder $sales): Builder => $sales->closedBetween($start, $end)->visibleTo($user)->inLocation($locationId),
                ],
                'gross_commission',
            )
            ->selectSub(
                $this->closedPercentQuery($user, $locationId, $start, $end),
                'closed_percent',
            )
            ->withCasts([
                'closed_volume' => 'decimal:2',
                'gross_commission' => 'decimal:2',
            ]);
    }

    /**
     * @return Builder<Lead>
     */
    private function closedPercentQuery(User $user, ?int $locationId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return Lead::query()
            ->selectClosedPercent()
            ->whereColumn('leads.lead_source_id', 'lead_sources.id')
            ->createdBetween($start, $end)
            ->visibleTo($user)
            ->inLocation($locationId);
    }

    private function exportCsv(): StreamedResponse
    {
        $records = $this->getTableQueryForExport()->get();

        return $this->downloadCsv(
            'lead-sources.csv',
            [
                'Name',
                'Closed sales',
                'Closed volume',
                'Gross commission',
                'Pending sales',
                'Total leads',
                'Closed %',
            ],
            $records->map(fn (LeadSource $record): array => [
                $record->name,
                (int) $record->closed_sales_count,
                $record->closed_volume,
                $record->gross_commission,
                (int) $record->pending_sales_count,
                (int) $record->leads_count,
                $this->formatClosedPercent($record->closed_percent),
            ]),
        );
    }

    private function salesIndexUrl(LeadSource $source): string
    {
        return SaleResource::getUrl('index', [
            'tab' => 'closed',
            'filters' => $this->filtersIncludingLocation([
                'source' => [
                    'value' => $source->id,
                ],
            ]),
        ], isAbsolute: false);
    }
}
