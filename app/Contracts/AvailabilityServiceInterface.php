<?php

namespace App\Contracts;

use App\Domain\ValueObjects\DateRange;
use Illuminate\Support\Collection;

interface AvailabilityServiceInterface
{
    /**
     * Read-only per-day availability for the given range.
     *
     * Each item shape:
     * [
     *   'date'      => 'YYYY-MM-DD',
     *   'capacity'  => int,
     *   'booked'    => int,
     *   'available' => int,
     * ]
     *
     * @return Collection<int, array{date:string,capacity:int,booked:int,available:int}>
     */
    public function calendar(DateRange $range): Collection;

    /**
     * Assert that every occupied day in the range has at least one space left.
     *
     * @param string|null $ignoreBookingId When amending, ignore this booking's own days.
     * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
     */
    public function assertRangeHasSpace(DateRange $range, ?string $ignoreBookingId = null): void;
}
