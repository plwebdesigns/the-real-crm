<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Widgets\Concerns\AppliesAnalyticsLocationFilter;
use App\Models\Lead;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class FirmLeadsStatsOverview extends StatsOverviewWidget
{
    use AppliesAnalyticsLocationFilter;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

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

        $totalCount = Lead::query()->visibleTo($user)->inLocation($locationId)->count();
        $workingCount = Lead::query()->visibleTo($user)->inLocation($locationId)->working()->count();
        $lostCount = Lead::query()->visibleTo($user)->inLocation($locationId)->lost()->count();
        $closedCount = Lead::query()->visibleTo($user)->inLocation($locationId)->closed()->count();
        $closedPercent = $totalCount === 0
            ? 0.0
            : ($closedCount / $totalCount) * 100;

        return [
            Stat::make('Total leads', (string) $totalCount)
                ->description('Entire pipeline')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('primary')
                ->url($this->leadsIndexUrl('all')),
            Stat::make('Working leads', (string) $workingCount)
                ->description('Contacted or qualified')
                ->descriptionIcon(Heroicon::OutlinedBolt)
                ->color('info')
                ->url($this->leadsIndexUrl('working')),
            Stat::make('Lost leads', (string) $lostCount)
                ->description('No longer active')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->url($this->leadsIndexUrl('lost')),
            Stat::make(
                'Percent of leads closed',
                Number::percentage($closedPercent, precision: 0) ?: '0%',
            )
                ->description('Have a closed sale')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->url($this->leadsIndexUrl('closed')),
        ];
    }

    private function leadsIndexUrl(string $tab): string
    {
        $parameters = [
            'tab' => $tab,
        ];
        $filters = $this->filtersIncludingLocation();

        if ($filters !== []) {
            $parameters['filters'] = $filters;
        }

        return LeadResource::getUrl('index', $parameters, isAbsolute: false);
    }
}
