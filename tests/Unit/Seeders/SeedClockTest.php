<?php

namespace Tests\Unit\Seeders;

use Carbon\CarbonImmutable;
use Database\Seeders\SeedClock;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class SeedClockTest extends TestCase
{
    public function test_spring_forward_gap_moves_to_the_next_valid_hour(): void
    {
        $clock = new SeedClock(new DateTimeZone('America/New_York'));

        $accepted = $clock->accept(CarbonImmutable::parse('2026-03-08 02:43:24', 'UTC'));

        $this->assertSame('2026-03-08 03:43:24', $accepted->format('Y-m-d H:i:s'));
    }

    public function test_existing_local_time_stays_unchanged(): void
    {
        $clock = new SeedClock(new DateTimeZone('America/New_York'));

        $accepted = $clock->accept(CarbonImmutable::parse('2026-03-08 15:04:05', 'UTC'));

        $this->assertSame('2026-03-08 15:04:05', $accepted->format('Y-m-d H:i:s'));
    }

    public function test_utc_keeps_the_spring_forward_hour(): void
    {
        $clock = new SeedClock(new DateTimeZone('UTC'));

        $accepted = $clock->accept(CarbonImmutable::parse('2026-03-08 02:43:24', 'UTC'));

        $this->assertSame('2026-03-08 02:43:24', $accepted->format('Y-m-d H:i:s'));
    }

    public function test_time_past_the_range_end_moves_back_before_the_gap(): void
    {
        $clock = new SeedClock(new DateTimeZone('America/New_York'));
        $end = CarbonImmutable::parse('2026-03-08 02:50:00', 'UTC');

        $accepted = $clock->between(
            CarbonImmutable::parse('2026-03-08 02:10:00', 'UTC'),
            $end,
        );

        $this->assertSame('01', $accepted->format('H'));
        $this->assertTrue($accepted->lessThanOrEqualTo($end));
        $this->assertSame(
            $accepted->format('Y-m-d H:i:s'),
            CarbonImmutable::parse($accepted->format('Y-m-d H:i:s'), 'America/New_York')->format('Y-m-d H:i:s'),
        );
    }

    public function test_system_timezone_uses_the_operating_system_zone(): void
    {
        $timezone = SeedClock::timezone('SYSTEM', 'America/New_York');

        $this->assertSame('America/New_York', $timezone->getName());
    }

    public function test_daylight_saving_abbreviation_uses_the_operating_system_zone(): void
    {
        $timezone = SeedClock::timezone('EDT', 'America/New_York');

        $this->assertSame('America/New_York', $timezone->getName());
    }

    public function test_named_session_timezone_is_kept(): void
    {
        $timezone = SeedClock::timezone('UTC', 'America/New_York');

        $this->assertSame('UTC', $timezone->getName());
    }

    public function test_unknown_timezone_falls_back_to_utc(): void
    {
        $timezone = SeedClock::timezone('Not/AZone', null);

        $this->assertSame('UTC', $timezone->getName());
    }
}
