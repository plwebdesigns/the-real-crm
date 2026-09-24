<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgentPerformanceTable;
use App\Filament\Widgets\FirmLeadsStatsOverview;
use App\Filament\Widgets\FirmSalesStatsOverview;
use App\Filament\Widgets\LeadSourcePerformanceTable;
use App\Models\Location;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Analytics extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'analytics';

    protected static ?string $title = 'Analytics';

    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_admin;
    }

    public function content(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema
            ->components([
                ...($user instanceof User && $user->is_super_admin ? [$this->getFiltersFormContentComponent()] : []),
                $this->getWidgetsContentComponent(),
            ]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('location_id')
                    ->label('Location')
                    ->options(fn (): array => Location::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('All locations'),
            ]);
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
            LeadSourcePerformanceTable::class,
        ];
    }
}
