<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class LeadsStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $assignedCount = $user->leads()->count();
        $workingCount = $user->leads()->working()->count();
        $lostCount = $user->leads()->lost()->count();
        $closedCount = $user->leads()->closed()->count();
        $closedPercent = $assignedCount === 0
            ? 0.0
            : ($closedCount / $assignedCount) * 100;

        return [
            Stat::make('Leads assigned', (string) $assignedCount)
                ->url($this->leadsIndexUrl('all', $user)),
            Stat::make('Working leads', (string) $workingCount)
                ->url($this->leadsIndexUrl('working', $user)),
            Stat::make('Lost leads', (string) $lostCount)
                ->url($this->leadsIndexUrl('lost', $user)),
            Stat::make(
                'Percent of leads closed',
                Number::percentage($closedPercent, precision: 0) ?: '0%',
            )
                ->url($this->leadsIndexUrl('closed', $user)),
        ];
    }

    private function leadsIndexUrl(string $tab, User $user): string
    {
        return LeadResource::getUrl('index', [
            'tab' => $tab,
            'filters' => [
                'agents' => [
                    'value' => $user->id,
                ],
            ],
        ], isAbsolute: false);
    }
}
