<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum AnalyticsPeriod: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case YearToDate = 'ytd';
    case LastYear = 'last_year';

    public function label(): string
    {
        return match ($this) {
            self::Month => 'This month',
            self::Quarter => 'This quarter',
            self::YearToDate => 'Year to date',
            self::LastYear => 'Last year',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $period) {
            $options[$period->value] = $period->label();
        }

        return $options;
    }

    public function description(): string
    {
        [$start, $end] = $this->range();

        return $start->format('M j').' – '.$end->format('M j, Y');
    }

    /**
     * Inclusive dates for this period. Open periods end today.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $today = $this->today();

        return match ($this) {
            self::Month => [$today->startOfMonth(), $today],
            self::Quarter => [$today->startOfQuarter(), $today],
            self::YearToDate => [$today->startOfYear(), $today],
            self::LastYear => [
                $today->subYear()->startOfYear(),
                $today->subYear()->endOfYear()->startOfDay(),
            ],
        };
    }

    /**
     * The same span immediately before this period.
     *
     * A partial month or quarter is compared with the same number of days
     * in the previous month or quarter, clamped so it does not spill past
     * that previous period. Year to date uses the same dates last year.
     * Last year is compared with the full year before it.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function previousRange(): array
    {
        [$start, $end] = $this->range();

        return match ($this) {
            self::Month => $this->previousMonthRange($end),
            self::Quarter => $this->previousQuarterRange($start, $end),
            self::YearToDate, self::LastYear => [$start->subYear(), $end->subYear()],
        };
    }

    /**
     * Same calendar months one year earlier, for the volume chart.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function comparisonYearRange(): array
    {
        [$start, $end] = $this->range();

        return [$start->subYear(), $end->subYear()];
    }

    /**
     * First day of each month that overlaps this period.
     *
     * @return list<CarbonImmutable>
     */
    public function months(): array
    {
        [$start, $end] = $this->range();
        $cursor = $start->startOfMonth();
        $last = $end->startOfMonth();
        $months = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $months[] = $cursor;
            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(now())->startOfDay();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function previousMonthRange(CarbonImmutable $end): array
    {
        $previousStart = $end->startOfMonth()->subMonth();
        $day = min($end->day, $previousStart->daysInMonth);

        return [$previousStart, $previousStart->day($day)];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function previousQuarterRange(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $previousStart = $start->subQuarter()->startOfQuarter();
        $days = (int) round($start->diffInDays($end));
        $previousEnd = $previousStart->addDays($days);
        $previousQuarterEnd = $previousStart->endOfQuarter()->startOfDay();

        if ($previousEnd->greaterThan($previousQuarterEnd)) {
            $previousEnd = $previousQuarterEnd;
        }

        return [$previousStart, $previousEnd];
    }
}
