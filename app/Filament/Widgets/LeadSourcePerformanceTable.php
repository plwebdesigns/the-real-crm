<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\LeadSource;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class LeadSourcePerformanceTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 4;

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
                    ->getStateUsing(function (LeadSource $record): string {
                        $leadsCount = (int) $record->leads_count;
                        $closedCount = (int) $record->closed_leads_count;
                        $closedPercent = $leadsCount === 0
                            ? 0.0
                            : ($closedCount / $leadsCount) * 100;

                        return Number::percentage($closedPercent, precision: 0) ?: '0%';
                    }),
            ])
            ->defaultSort('closed_volume', 'desc');
    }

    /**
     * @return Builder<LeadSource>
     */
    private function sourcesQuery(): Builder
    {
        $year = now()->year;
        $user = auth()->user();

        if (! $user instanceof User) {
            return LeadSource::query()->whereRaw('0 = 1');
        }

        return LeadSource::query()
            ->withCount([
                'sales as closed_sales_count' => fn (Builder $sales): Builder => $sales->closedInYear($year)->visibleTo($user),
                'sales as pending_sales_count' => fn (Builder $sales): Builder => $sales->pending()->visibleTo($user),
                'leads as leads_count' => fn (Builder $leads): Builder => $leads->visibleTo($user),
                'leads as closed_leads_count' => fn (Builder $leads): Builder => $leads->closed()->visibleTo($user),
            ])
            ->withSum(
                [
                    'sales as closed_volume' => fn (Builder $sales): Builder => $sales->closedInYear($year)->visibleTo($user),
                ],
                'price',
            )
            ->withSum(
                [
                    'sales as gross_commission' => fn (Builder $sales): Builder => $sales->closedInYear($year)->visibleTo($user),
                ],
                'gross_commission',
            )
            ->withCasts([
                'closed_volume' => 'decimal:2',
                'gross_commission' => 'decimal:2',
            ]);
    }

    private function salesIndexUrl(LeadSource $source): string
    {
        return SaleResource::getUrl('index', [
            'tab' => 'closed',
            'filters' => [
                'source' => [
                    'value' => $source->id,
                ],
            ],
        ], isAbsolute: false);
    }
}
