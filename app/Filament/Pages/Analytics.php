<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgentPerformanceTable;
use App\Filament\Widgets\FirmLeadsStatsOverview;
use App\Filament\Widgets\FirmSalesStatsOverview;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Analytics extends Dashboard
{
    protected static string $routePath = 'analytics';

    protected static ?string $title = 'Analytics';

    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_admin;
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            FirmSalesStatsOverview::class,
            FirmLeadsStatsOverview::class,
            AgentPerformanceTable::class,
        ];
    }
}
