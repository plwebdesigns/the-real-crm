<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Widgets\Concerns\AppliesAnalyticsLocationFilter;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class FirmSalesStatsOverview extends StatsOverviewWidget
{
    use AppliesAnalyticsLocationFilter;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_admin;
    }

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $locationId = $this->selectedLocationId();
        [$start, $end] = $this->selectedPeriod()->range();
        [$previousStart, $previousEnd] = $this->selectedPeriod()->previousRange();
        $closed = $this->closedSummary($user, $locationId, $start, $end);
        $previous = $this->closedSummary($user, $locationId, $previousStart, $previousEnd);
        $pending = Sale::query()
            ->visibleTo($user)
            ->inLocation($locationId)
            ->pending()
            ->toBase()
            ->selectRaw('count(*) as pending_count')
            ->selectRaw('coalesce(sum(sales.price), 0) as pending_volume')
            ->selectRaw('coalesce(sum(sales.gross_commission), 0) as pending_commission')
            ->first();

        $closedCount = $this->describeAgainstPriorPeriod(
            (float) ($closed->closed_count ?? 0),
            (float) ($previous->closed_count ?? 0),
        );
        $closedVolume = $this->describeAgainstPriorPeriod(
            (float) ($closed->closed_volume ?? 0),
            (float) ($previous->closed_volume ?? 0),
        );
        $grossCommission = $this->describeAgainstPriorPeriod(
            (float) ($closed->gross_commission ?? 0),
            (float) ($previous->gross_commission ?? 0),
        );
        $brokerageFees = $this->describeAgainstPriorPeriod(
            (float) ($closed->brokerage_fees ?? 0),
            (float) ($previous->brokerage_fees ?? 0),
        );

        return [
            Stat::make('Closed sales', (string) ($closed->closed_count ?? 0))
                ->description($closedCount['description'])
                ->descriptionIcon($closedCount['icon'])
                ->descriptionColor($closedCount['color'])
                ->color('success')
                ->url($this->salesIndexUrl('closed')),
            Stat::make(
                'Closed volume',
                Number::currency((float) ($closed->closed_volume ?? 0), 'USD'),
            )
                ->description($closedVolume['description'])
                ->descriptionIcon($closedVolume['icon'])
                ->descriptionColor($closedVolume['color'])
                ->color('success')
                ->url($this->salesIndexUrl('closed')),
            Stat::make(
                'Gross commission',
                Number::currency((float) ($closed->gross_commission ?? 0), 'USD'),
            )
                ->description($grossCommission['description'])
                ->descriptionIcon($grossCommission['icon'])
                ->descriptionColor($grossCommission['color'])
                ->color('success')
                ->url($this->salesIndexUrl('closed')),
            Stat::make(
                'Brokerage fees',
                Number::currency((float) ($closed->brokerage_fees ?? 0), 'USD'),
            )
                ->description($brokerageFees['description'])
                ->descriptionIcon($brokerageFees['icon'])
                ->descriptionColor($brokerageFees['color'])
                ->color('success')
                ->url($this->salesIndexUrl('closed')),
            Stat::make(
                'Pending sales',
                (string) ($pending->pending_count ?? 0),
            )
                ->description('Open pipeline')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning')
                ->url($this->salesIndexUrl('pending')),
            Stat::make(
                'Pending volume',
                Number::currency((float) ($pending->pending_volume ?? 0), 'USD'),
            )
                ->description(
                    'Open pipeline · '.Number::currency((float) ($pending->pending_commission ?? 0), 'USD').' gross commission',
                )
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning')
                ->url($this->salesIndexUrl('pending')),
        ];
    }

    private function closedSummary(User $user, ?int $locationId, CarbonInterface $start, CarbonInterface $end): ?object
    {
        return Sale::query()
            ->visibleTo($user)
            ->inLocation($locationId)
            ->closedBetween($start, $end)
            ->toBase()
            ->selectRaw('count(*) as closed_count')
            ->selectRaw('coalesce(sum(sales.price), 0) as closed_volume')
            ->selectRaw('coalesce(sum(sales.gross_commission), 0) as gross_commission')
            ->selectRaw('coalesce(sum(sales.brokerage_fee), 0) as brokerage_fees')
            ->first();
    }

    private function salesIndexUrl(string $tab): string
    {
        $parameters = [
            'tab' => $tab,
        ];
        $filters = $this->filtersIncludingLocation();

        if ($filters !== []) {
            $parameters['filters'] = $filters;
        }

        return SaleResource::getUrl('index', $parameters, isAbsolute: false);
    }
}
