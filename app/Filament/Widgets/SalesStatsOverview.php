<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class SalesStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $closed = $user->sales()
            ->closedInYear(now()->year)
            ->toBase()
            ->selectRaw('count(*) as closed_count')
            ->selectRaw('coalesce(sum(sales.price), 0) as closed_volume')
            ->selectRaw('coalesce(sum(sale_user.net_commission), 0) as net_commission')
            ->first();

        return [
            Stat::make('Closed sales', (string) ($closed->closed_count ?? 0))
                ->description('Year to date')
                ->url($this->salesIndexUrl('closed', $user)),
            Stat::make(
                'Closed volume',
                Number::currency((float) ($closed->closed_volume ?? 0), 'USD'),
            )->description('Year to date'),
            Stat::make(
                'Net commission',
                Number::currency((float) ($closed->net_commission ?? 0), 'USD'),
            )->description('Year to date'),
            Stat::make(
                'Pending sales',
                (string) $user->sales()->pending()->count(),
            )
                ->description('Open pipeline')
                ->url($this->salesIndexUrl('pending', $user)),
        ];
    }

    private function salesIndexUrl(string $tab, User $user): string
    {
        return SaleResource::getUrl('index', [
            'tab' => $tab,
            'filters' => [
                'agents' => [
                    'value' => $user->id,
                ],
            ],
        ], isAbsolute: false);
    }
}
