<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\AnalyticsPeriod;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait AppliesAnalyticsLocationFilter
{
    use InteractsWithPageFilters;

    protected function selectedLocationId(): ?int
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->is_super_admin) {
            return null;
        }

        $locationId = $this->pageFilters['location_id'] ?? null;

        if (! is_numeric($locationId)) {
            return null;
        }

        return (int) $locationId;
    }

    protected function selectedPeriod(): AnalyticsPeriod
    {
        $period = $this->pageFilters['period'] ?? null;

        if (! is_string($period)) {
            return AnalyticsPeriod::YearToDate;
        }

        return AnalyticsPeriod::tryFrom($period) ?? AnalyticsPeriod::YearToDate;
    }

    /**
     * @param  array<string, array<string, mixed>>  $filters
     * @return array<string, array<string, mixed>>
     */
    protected function filtersIncludingLocation(array $filters = []): array
    {
        $locationId = $this->selectedLocationId();

        if ($locationId === null) {
            return $filters;
        }

        $filters['location_id'] = [
            'value' => $locationId,
        ];

        return $filters;
    }

    protected function formatClosedPercent(mixed $percent): string
    {
        return Number::percentage((float) $percent, precision: 0) ?: '0%';
    }

    /**
     * Describe this period against the previous matching period.
     *
     * `$tone` is `higher` when an increase is an improvement, `lower` when a
     * decrease is an improvement, and `neutral` when the direction is only
     * informational.
     *
     * @return array{description: string, color: string, icon: Heroicon}
     */
    protected function describeAgainstPriorPeriod(float $current, float $previous, string $tone = 'higher'): array
    {
        $range = $this->selectedPeriod()->description();

        if (abs($previous) < 0.00001) {
            if (abs($current) < 0.00001) {
                return $this->flatComparison($range);
            }

            return [
                'description' => $range.' · Up from none in the prior period',
                'color' => $this->comparisonColor(improved: $tone !== 'lower'),
                'icon' => Heroicon::OutlinedArrowTrendingUp,
            ];
        }

        $change = ($current - $previous) / abs($previous);
        $percent = Number::percentage(abs($change) * 100, precision: 0) ?: '0%';

        if ($percent === '0%') {
            return $this->flatComparison($range);
        }

        $up = $current > $previous;
        $improved = match ($tone) {
            'lower' => ! $up,
            'neutral' => false,
            default => $up,
        };

        return [
            'description' => $range.' · '.$percent.' '.($up ? 'above' : 'below').' prior period',
            'color' => $tone === 'neutral' ? 'gray' : $this->comparisonColor($improved),
            'icon' => $up ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown,
        ];
    }

    /**
     * @param  list<string>  $headings
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    protected function downloadCsv(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array{description: string, color: string, icon: Heroicon}
     */
    private function flatComparison(string $range): array
    {
        return [
            'description' => $range.' · Flat vs prior period',
            'color' => 'gray',
            'icon' => Heroicon::OutlinedMinus,
        ];
    }

    private function comparisonColor(bool $improved): string
    {
        return $improved ? 'success' : 'danger';
    }
}
