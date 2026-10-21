<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Widgets\Concerns\AppliesAnalyticsLocationFilter;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
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
        [$start, $end] = $this->selectedPeriod()->range();
        [$previousStart, $previousEnd] = $this->selectedPeriod()->previousRange();
        $current = $this->cohortCounts($user, $locationId, $start, $end);
        $previous = $this->cohortCounts($user, $locationId, $previousStart, $previousEnd);
        $currentMedian = $this->medianDaysToClose($user, $locationId, $start, $end);
        $previousMedian = $this->medianDaysToClose($user, $locationId, $previousStart, $previousEnd);
        $median = $this->medianComparison($currentMedian, $previousMedian);
        $staleCount = Lead::query()
            ->visibleTo($user)
            ->inLocation($locationId)
            ->staleWorking()
            ->count();

        $total = $this->describeAgainstPriorPeriod((float) $current['total'], (float) $previous['total'], 'neutral');
        $working = $this->describeAgainstPriorPeriod((float) $current['working'], (float) $previous['working'], 'neutral');
        $lost = $this->describeAgainstPriorPeriod((float) $current['lost'], (float) $previous['lost'], 'lower');
        $closedPercent = $this->describeAgainstPriorPeriod($current['closed_percent'], $previous['closed_percent']);

        return [
            Stat::make('Total leads', (string) $current['total'])
                ->description($total['description'])
                ->descriptionIcon($total['icon'])
                ->descriptionColor($total['color'])
                ->color('primary')
                ->url($this->leadsIndexUrl('all')),
            Stat::make('Working leads', (string) $current['working'])
                ->description($working['description'])
                ->descriptionIcon($working['icon'])
                ->descriptionColor($working['color'])
                ->color('info')
                ->url($this->leadsIndexUrl('working')),
            Stat::make('Lost leads', (string) $current['lost'])
                ->description($lost['description'])
                ->descriptionIcon($lost['icon'])
                ->descriptionColor($lost['color'])
                ->color('danger')
                ->url($this->leadsIndexUrl('lost')),
            Stat::make(
                'Percent of leads closed',
                Number::percentage($current['closed_percent'], precision: 0) ?: '0%',
            )
                ->description($closedPercent['description'])
                ->descriptionIcon($closedPercent['icon'])
                ->descriptionColor($closedPercent['color'])
                ->color('success')
                ->url($this->leadsIndexUrl('closed')),
            Stat::make('Median days to close', $this->formatDays($currentMedian))
                ->description($median['description'])
                ->descriptionIcon($median['icon'])
                ->descriptionColor($median['color'])
                ->color('success')
                ->url($this->salesIndexUrl('closed')),
            Stat::make('Stale working leads', (string) $staleCount)
                ->description('No activity in 14 days')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($staleCount > 0 ? 'warning' : 'gray')
                ->url($this->leadsIndexUrl('working')),
        ];
    }

    /**
     * Working is contacted or qualified, matching {@see Lead::working()}.
     * Lost matches {@see Lead::lost()}. Closed means the lead has a closed sale.
     *
     * @return array{total: int, working: int, lost: int, closed_percent: float}
     */
    private function cohortCounts(User $user, ?int $locationId, CarbonInterface $start, CarbonInterface $end): array
    {
        $row = Lead::query()
            ->visibleTo($user)
            ->inLocation($locationId)
            ->createdBetween($start, $end)
            ->leftJoin('lead_statuses', 'lead_statuses.id', '=', 'leads.lead_status_id')
            ->toBase()
            ->selectRaw('count(*) as total_count')
            ->selectRaw("coalesce(sum(case when lead_statuses.slug in ('contacted', 'qualified') then 1 else 0 end), 0) as working_count")
            ->selectRaw("coalesce(sum(case when lead_statuses.slug = 'lost' then 1 else 0 end), 0) as lost_count")
            ->selectRaw('coalesce(sum(case when '.Lead::closedSaleExistsSql().' then 1 else 0 end), 0) as closed_count')
            ->first();

        $total = (int) ($row->total_count ?? 0);
        $closed = (int) ($row->closed_count ?? 0);

        return [
            'total' => $total,
            'working' => (int) ($row->working_count ?? 0),
            'lost' => (int) ($row->lost_count ?? 0),
            'closed_percent' => $total === 0 ? 0.0 : ($closed / $total) * 100,
        ];
    }

    private function medianDaysToClose(User $user, ?int $locationId, CarbonInterface $start, CarbonInterface $end): ?float
    {
        $days = Sale::query()
            ->visibleTo($user)
            ->inLocation($locationId)
            ->closedBetween($start, $end)
            ->join('leads', 'leads.id', '=', 'sales.lead_id')
            ->toBase()
            ->select(['sales.closed_at', 'leads.created_at'])
            ->get()
            ->map(function (object $sale): int {
                $createdAt = CarbonImmutable::parse($sale->created_at)->startOfDay();
                $closedAt = CarbonImmutable::parse($sale->closed_at)->startOfDay();

                return (int) round($createdAt->diffInDays($closedAt));
            })
            ->sort()
            ->values();

        return $this->median($days);
    }

    /**
     * @param  Collection<int, int>  $days
     */
    private function median(Collection $days): ?float
    {
        $count = $days->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $days[$middle];
        }

        return ((float) $days[$middle - 1] + (float) $days[$middle]) / 2;
    }

    private function formatDays(?float $days): string
    {
        if ($days === null) {
            return '—';
        }

        $rounded = round($days, 1);
        $formatted = fmod($rounded, 1.0) === 0.0
            ? (string) (int) $rounded
            : number_format($rounded, 1, '.', '');

        return $formatted.' '.($formatted === '1' ? 'day' : 'days');
    }

    /**
     * @return array{description: string, color: string, icon: Heroicon}
     */
    private function medianComparison(?float $current, ?float $previous): array
    {
        $range = $this->selectedPeriod()->description();

        if ($current === null) {
            return [
                'description' => $range.' · No closed sales',
                'color' => 'gray',
                'icon' => Heroicon::OutlinedMinus,
            ];
        }

        if ($previous === null) {
            return [
                'description' => $range.' · No closed sales in the prior period',
                'color' => 'gray',
                'icon' => Heroicon::OutlinedMinus,
            ];
        }

        return $this->describeAgainstPriorPeriod($current, $previous, 'lower');
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
