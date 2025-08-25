<?php

namespace App\Contracts;

use App\Domain\ValueObjects\DateRange;
use Brick\Money\Money;


/**
 * Contract for quoting price over a date range.
 * The return shape is stable and consumed by PriceQuoteResource.
 */
interface PricingServiceInterface
{
    /**
     * Quote a price for the given date range.
     *
     * @param  DateRange $range
     * @return array{
     *   currency: string,
     *   total: Money,
     *   breakdown: array<int, array{
     *     date: string,
     *     season: string,
     *     day_type: 'weekday'|'weekend',
     *     amount_minor: int
     *   }>
     * }
     */
    public function quote(DateRange $range): array;
}
