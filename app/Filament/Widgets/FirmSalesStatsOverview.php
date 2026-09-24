<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Widgets\Concerns\AppliesAnalyticsLocationFilter;
use App\Models\Sale;
use App\Models\User;
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

        $closed = Sale::query()
            ->visibleTo($user)
            ->inLocation($locationId)
            ->closedInYear(now()->year)
            ->toBase()
            ->selectRaw('count(*) as closed_count')
            ->selectRaw('coalesce(sum(sales.price), 0) as closed_volume')
            ->selectRaw('coalesce(sum(sales.gross_commission), 0) as gross_commission')
            ->first();

        return [
            Stat::make('Closed sales', (string) ($closed->closed_count ?? 0))
                ->description('Year to date')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->url($this->salesIndexUrl('closed')),
            Stat::make(
                'Closed volume',
                Number::currency((float) ($closed->closed_volume ?? 0), 'USD'),
            )
                ->description('Year to date')
                ->descriptionIcon(Heroicon::OutlinedHomeModern)
                ->color('success'),
            Stat::make(
                'Gross commission',
                Number::currency((float) ($closed->gross_commission ?? 0), 'USD'),
            )
                ->description('Year to date')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make(
                'Pending sales',
                (string) Sale::query()->visibleTo($user)->inLocation($locationId)->pending()->count(),
            )
                ->description('Open pipeline')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning')
                ->url($this->salesIndexUrl('pending')),
        ];
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
