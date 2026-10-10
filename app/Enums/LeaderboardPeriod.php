<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum LeaderboardPeriod: string
{
    case FirstQuarter = 'q1';
    case SecondQuarter = 'q2';
    case ThirdQuarter = 'q3';
    case FourthQuarter = 'q4';
    case YearToDate = 'ytd';

    public function label(): string
    {
        $year = $this->today()->year;

        return match ($this) {
            self::FirstQuarter => "Q1 {$year}",
            self::SecondQuarter => "Q2 {$year}",
            self::ThirdQuarter => "Q3 {$year}",
            self::FourthQuarter => "Q4 {$year}",
            self::YearToDate => 'Year to date',
        };
    }

    public function description(): string
    {
        [$start, $end] = $this->range();

        return $start->format('M j').' – '.$end->format('M j, Y');
    }

    public function hasStarted(): bool
    {
        return $this->startsAt()->lessThanOrEqualTo($this->today());
    }

    /**
     * Inclusive close dates for this period, ending no later than today.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $today = $this->today();
        $end = $this->endsAt();

        if ($end->greaterThan($today)) {
            $end = $today;
        }

        return [$this->startsAt(), $end];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(now())->startOfDay();
    }

    private function startsAt(): CarbonImmutable
    {
        $month = match ($this) {
            self::FirstQuarter, self::YearToDate => 1,
            self::SecondQuarter => 4,
            self::ThirdQuarter => 7,
            self::FourthQuarter => 10,
        };

        return $this->date($month, 1);
    }

    private function endsAt(): CarbonImmutable
    {
        return match ($this) {
            self::FirstQuarter => $this->date(3, 31),
            self::SecondQuarter => $this->date(6, 30),
            self::ThirdQuarter => $this->date(9, 30),
            self::FourthQuarter => $this->date(12, 31),
            self::YearToDate => $this->today(),
        };
    }

    private function date(int $month, int $day): CarbonImmutable
    {
        return CarbonImmutable::parse(sprintf('%d-%02d-%02d', $this->today()->year, $month, $day));
    }
}
