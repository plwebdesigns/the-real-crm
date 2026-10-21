<?php

namespace App\Filament\Pages;

use App\Enums\AnalyticsPeriod;
use App\Filament\Widgets\AgentPerformanceTable;
use App\Filament\Widgets\ClosedVolumeChart;
use App\Filament\Widgets\FirmLeadsStatsOverview;
use App\Filament\Widgets\FirmSalesStatsOverview;
use App\Filament\Widgets\LeadSourcePerformanceTable;
use App\Filament\Widgets\LeadTypePerformanceTable;
use App\Filament\Widgets\LocationPerformanceTable;
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

    public function booted(): void
    {
        $period = $this->filters['period'] ?? null;

        if (is_string($period) && AnalyticsPeriod::tryFrom($period) instanceof AnalyticsPeriod) {
            return;
        }

        $filters = $this->filters ?? [];
        $filters['period'] = AnalyticsPeriod::YearToDate->value;
        $this->filters = $filters;

        if ($this->persistsFiltersInSession()) {
            session()->put($this->getFiltersSessionKey(), $this->filters);
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFiltersFormContentComponent(),
                $this->getWidgetsContentComponent(),
            ]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('period')
                    ->label('Period')
                    ->options(AnalyticsPeriod::options())
                    ->default(AnalyticsPeriod::YearToDate->value)
                    ->selectablePlaceholder(false)
                    ->native(false),
                Select::make('location_id')
                    ->label('Location')
                    ->options(fn (): array => Location::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('All locations')
                    ->visible(function (): bool {
                        $user = auth()->user();

                        return $user instanceof User && $user->is_super_admin;
                    }),
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
            ClosedVolumeChart::class,
            ...($this->showsLocationComparison() ? [LocationPerformanceTable::class] : []),
            LeadTypePerformanceTable::class,
            AgentPerformanceTable::class,
            LeadSourcePerformanceTable::class,
        ];
    }

    private function showsLocationComparison(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->is_super_admin) {
            return false;
        }

        $locationId = $this->filters['location_id'] ?? null;

        return ! is_numeric($locationId);
    }
}
