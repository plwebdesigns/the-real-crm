<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AppliesAnalyticsLocationFilter;
use App\Models\Lead;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LocationPerformanceTable extends TableWidget
{
    use AppliesAnalyticsLocationFilter;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_super_admin;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Offices')
            ->query($this->locationsQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('closed_percent')
                    ->label('Closed %')
                    ->color('success')
                    ->sortable()
                    ->getStateUsing(fn (Location $record): string => $this->formatClosedPercent($record->closed_percent)),
                TextColumn::make('pending_sales_count')
                    ->label('Pending sales')
                    ->numeric()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('pending_volume')
                    ->label('Pending volume')
                    ->money('USD')
                    ->default(0)
                    ->color('warning')
                    ->sortable(),
            ])
            ->defaultSort('closed_volume', 'desc')
            ->paginated(false);
    }

    /**
     * @return Builder<Location>
     */
    private function locationsQuery(): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return Location::query()->whereRaw('0 = 1');
        }

        [$start, $end] = $this->selectedPeriod()->range();

        return Location::query()
            ->withCount([
                'sales as closed_sales_count' => fn (Builder $sales): Builder => $sales->closedBetween($start, $end)->visibleTo($user),
                'sales as pending_sales_count' => fn (Builder $sales): Builder => $sales->pending()->visibleTo($user),
            ])
            ->withSum(
                [
                    'sales as closed_volume' => fn (Builder $sales): Builder => $sales->closedBetween($start, $end)->visibleTo($user),
                ],
                'price',
            )
            ->withSum(
                [
                    'sales as gross_commission' => fn (Builder $sales): Builder => $sales->closedBetween($start, $end)->visibleTo($user),
                ],
                'gross_commission',
            )
            ->withSum(
                [
                    'sales as pending_volume' => fn (Builder $sales): Builder => $sales->pending()->visibleTo($user),
                ],
                'price',
            )
            ->selectSub(
                $this->closedPercentQuery($user, $start, $end),
                'closed_percent',
            )
            ->withCasts([
                'closed_volume' => 'decimal:2',
                'gross_commission' => 'decimal:2',
                'pending_volume' => 'decimal:2',
            ]);
    }

    /**
     * @return Builder<Lead>
     */
    private function closedPercentQuery(User $user, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return Lead::query()
            ->selectClosedPercent()
            ->whereColumn('leads.location_id', 'locations.id')
            ->createdBetween($start, $end)
            ->visibleTo($user);
    }
}
