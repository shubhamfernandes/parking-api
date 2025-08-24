<?php

namespace Tests\Unit;

use App\Domain\ValueObjects\DateRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    public function test_throws_if_to_is_not_after_from(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DateRange(
            CarbonImmutable::parse('2025-08-22 00:00:00')->startOfDay(),
            CarbonImmutable::parse('2025-08-22 00:00:00')
        );
    }

    public function test_generates_occupied_days_excluding_checkout(): void
    {
        $range = new DateRange(
            CarbonImmutable::parse('2025-08-22')->startOfDay(),
            CarbonImmutable::parse('2025-08-25 09:00:00')
        );

        $days = iterator_to_array($range->eachOccupiedDay());
        $this->assertSame(['2025-08-22','2025-08-23','2025-08-24'], $days);
    }

    public function test_time_of_day_boundaries_do_not_change_days(): void
    {
        $r1 = new DateRange(
            CarbonImmutable::parse('2025-08-22')->startOfDay(),
            CarbonImmutable::parse('2025-08-23 00:00:01')
        );
        $r2 = new DateRange(
            CarbonImmutable::parse('2025-08-22')->startOfDay(),
            CarbonImmutable::parse('2025-08-23 23:59:59')
        );
        $this->assertSame(iterator_to_array($r1->eachOccupiedDay()), iterator_to_array($r2->eachOccupiedDay()));
    }
}
