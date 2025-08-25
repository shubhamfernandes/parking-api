<?php

namespace App\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $fromDate,   // date at 00:00
        public CarbonImmutable $toDateTime  // datetime
    ) {
        if ($toDateTime->lessThanOrEqualTo($fromDate->startOfDay())) {
            throw new InvalidArgumentException('To must be after From.');
        }
    }

    /**
     * Yields each occupied calendar day, counting days in [from, to),
     * i.e., excludes the pickup day.
     *
     * @return \Generator<string>
     */
    public function eachOccupiedDay(): \Generator
    {
        $current = $this->fromDate->startOfDay();
        $checkoutDayStart = $this->toDateTime->startOfDay();

        while ($current->lessThan($checkoutDayStart)) {
            yield $current->toDateString();
            $current = $current->addDay();
        }
    }
}
