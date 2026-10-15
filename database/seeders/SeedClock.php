<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateInvalidTimeZoneException;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Connection;

/**
 * Seed timestamps whose clock time exists in the database session timezone.
 *
 * MySQL rejects TIMESTAMP values that fall in a daylight-saving gap, such as
 * 02:43 on the morning the clocks spring forward.
 */
class SeedClock
{
    public function __construct(private DateTimeZone $databaseTimezone) {}

    public static function fromConnection(Connection $connection): self
    {
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return new self(new DateTimeZone('UTC'));
        }

        $row = $connection->selectOne('select @@session.time_zone as session_time_zone');
        $sessionTimeZone = 'SYSTEM';

        if (is_object($row) && isset($row->session_time_zone)) {
            $sessionTimeZone = (string) $row->session_time_zone;
        }

        return new self(self::timezone($sessionTimeZone, self::operatingSystemTimezone()));
    }

    public static function timezone(string $sessionTimeZone, ?string $operatingSystemTimeZone): DateTimeZone
    {
        $name = $sessionTimeZone;

        if ($name === 'SYSTEM' || self::isFixedAbbreviation($name)) {
            $name = $operatingSystemTimeZone ?? 'UTC';
        }

        try {
            return new DateTimeZone($name);
        } catch (DateInvalidTimeZoneException) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * Inclusive bounds for a quarter of the current year, ending no later than now.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function quarterBounds(int $quarter): array
    {
        $start = now()->startOfYear()->addQuarters($quarter - 1)->startOfDay();
        $end = $start->copy()->endOfQuarter()->min(now());

        return [$start, $end];
    }

    public function between(CarbonInterface $start, CarbonInterface $end): CarbonImmutable
    {
        if ($end->lessThanOrEqualTo($start)) {
            return $this->accept($start);
        }

        $timestamp = CarbonImmutable::parse(fake()->dateTimeBetween($start, $end));
        $accepted = $this->accept($timestamp);

        if ($accepted->greaterThan($end)) {
            return $this->accept($timestamp->subHour());
        }

        return $accepted;
    }

    public function accept(DateTimeInterface $timestamp): CarbonImmutable
    {
        $utc = CarbonImmutable::parse($timestamp)->utc();
        $clock = $utc->format('Y-m-d H:i:s');
        $zoned = CarbonImmutable::parse($clock, $this->databaseTimezone);

        if ($zoned->format('Y-m-d H:i:s') !== $clock) {
            $clock = $zoned->format('Y-m-d H:i:s');
        }

        return CarbonImmutable::parse($clock, 'UTC');
    }

    private static function operatingSystemTimezone(): ?string
    {
        $path = readlink('/etc/localtime');

        if (! is_string($path) || preg_match('#zoneinfo/(.+)$#', $path, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private static function isFixedAbbreviation(string $name): bool
    {
        return preg_match('/^[A-Za-z]{2,5}$/', $name) === 1
            && ! in_array(strtoupper($name), ['UTC', 'GMT'], true);
    }
}
