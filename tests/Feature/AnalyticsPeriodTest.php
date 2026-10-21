<?php

namespace Tests\Feature;

use App\Enums\AnalyticsPeriod;
use Tests\TestCase;

class AnalyticsPeriodTest extends TestCase
{
    public function test_month_range_ends_today_and_previous_month_uses_the_same_day(): void
    {
        $this->travelTo('2026-10-20 15:00:00');

        $this->assertSame('2026-10-01', AnalyticsPeriod::Month->range()[0]->toDateString());
        $this->assertSame('2026-10-20', AnalyticsPeriod::Month->range()[1]->toDateString());
        $this->assertSame('2026-09-01', AnalyticsPeriod::Month->previousRange()[0]->toDateString());
        $this->assertSame('2026-09-20', AnalyticsPeriod::Month->previousRange()[1]->toDateString());
        $this->assertSame('Oct 1 – Oct 20, 2026', AnalyticsPeriod::Month->description());
    }

    public function test_month_range_clamps_to_the_shorter_previous_month(): void
    {
        $this->travelTo('2026-03-31 15:00:00');

        $this->assertSame('2026-02-01', AnalyticsPeriod::Month->previousRange()[0]->toDateString());
        $this->assertSame('2026-02-28', AnalyticsPeriod::Month->previousRange()[1]->toDateString());
    }

    public function test_quarter_range_uses_the_same_offset_in_the_previous_quarter(): void
    {
        $this->travelTo('2026-10-20 15:00:00');

        $this->assertSame('2026-10-01', AnalyticsPeriod::Quarter->range()[0]->toDateString());
        $this->assertSame('2026-10-20', AnalyticsPeriod::Quarter->range()[1]->toDateString());
        $this->assertSame('2026-07-01', AnalyticsPeriod::Quarter->previousRange()[0]->toDateString());
        $this->assertSame('2026-07-20', AnalyticsPeriod::Quarter->previousRange()[1]->toDateString());
    }

    public function test_full_quarter_does_not_spill_into_the_next_quarter(): void
    {
        $this->travelTo('2026-09-30 15:00:00');

        $this->assertSame('2026-07-01', AnalyticsPeriod::Quarter->range()[0]->toDateString());
        $this->assertSame('2026-09-30', AnalyticsPeriod::Quarter->range()[1]->toDateString());
        $this->assertSame('2026-04-01', AnalyticsPeriod::Quarter->previousRange()[0]->toDateString());
        $this->assertSame('2026-06-30', AnalyticsPeriod::Quarter->previousRange()[1]->toDateString());
    }

    public function test_year_to_date_matches_the_same_dates_last_year(): void
    {
        $this->travelTo('2026-10-20 15:00:00');

        $this->assertSame('2026-01-01', AnalyticsPeriod::YearToDate->range()[0]->toDateString());
        $this->assertSame('2026-10-20', AnalyticsPeriod::YearToDate->range()[1]->toDateString());
        $this->assertSame('2025-01-01', AnalyticsPeriod::YearToDate->previousRange()[0]->toDateString());
        $this->assertSame('2025-10-20', AnalyticsPeriod::YearToDate->previousRange()[1]->toDateString());
        $this->assertSame('2025-01-01', AnalyticsPeriod::YearToDate->comparisonYearRange()[0]->toDateString());
        $this->assertSame('2025-10-20', AnalyticsPeriod::YearToDate->comparisonYearRange()[1]->toDateString());
    }

    public function test_last_year_is_the_full_previous_calendar_year(): void
    {
        $this->travelTo('2026-10-20 15:00:00');

        $this->assertSame('2025-01-01', AnalyticsPeriod::LastYear->range()[0]->toDateString());
        $this->assertSame('2025-12-31', AnalyticsPeriod::LastYear->range()[1]->toDateString());
        $this->assertSame('2024-01-01', AnalyticsPeriod::LastYear->previousRange()[0]->toDateString());
        $this->assertSame('2024-12-31', AnalyticsPeriod::LastYear->previousRange()[1]->toDateString());
        $this->assertSame('Jan 1 – Dec 31, 2025', AnalyticsPeriod::LastYear->description());
    }
}
