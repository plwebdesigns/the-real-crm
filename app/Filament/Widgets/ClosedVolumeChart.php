<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AppliesAnalyticsLocationFilter;
use App\Models\Sale;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClosedVolumeChart extends ChartWidget
{
    use AppliesAnalyticsLocationFilter;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Closed volume';

    protected ?string $maxHeight = '16rem';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_admin;
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->selectedPeriod()->description();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $period = $this->selectedPeriod();
        [$start, $end] = $period->range();
        [$priorStart, $priorEnd] = $period->comparisonYearRange();
        $monthKey = $this->monthKeyExpression();
        $volumes = Sale::query()
            ->visibleTo($user)
            ->inLocation($this->selectedLocationId())
            ->where(function (Builder $query) use ($start, $end, $priorStart, $priorEnd): void {
                $query->where(fn (Builder $query): Builder => $query->closedBetween($start, $end))
                    ->orWhere(fn (Builder $query): Builder => $query->closedBetween($priorStart, $priorEnd));
            })
            ->toBase()
            ->selectRaw($monthKey.' as month_key')
            ->selectRaw('coalesce(sum(sales.price), 0) as volume')
            ->groupByRaw($monthKey)
            ->pluck('volume', 'month_key');

        $labels = [];
        $current = [];
        $prior = [];

        foreach ($period->months() as $month) {
            $labels[] = $month->format('M');
            $current[] = (float) ($volumes[$month->format('Y-m')] ?? 0);
            $prior[] = (float) ($volumes[$month->subYear()->format('Y-m')] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => (string) $start->year,
                    'data' => $current,
                    'backgroundColor' => 'rgba(132, 204, 22, 0.75)',
                    'borderColor' => 'rgba(132, 204, 22, 1)',
                ],
                [
                    'label' => (string) $priorStart->year,
                    'data' => $prior,
                    'backgroundColor' => 'rgba(148, 163, 184, 0.75)',
                    'borderColor' => 'rgba(148, 163, 184, 1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    private function monthKeyExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', sales.closed_at)",
            default => "date_format(sales.closed_at, '%Y-%m')",
        };
    }
}
