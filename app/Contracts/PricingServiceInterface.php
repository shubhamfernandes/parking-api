<?php

namespace App\Contracts;

use App\Domain\ValueObjects\DateRange;

/**
 * Contract for quoting price over a date range.
 * Return shape is stable and used by PriceQuoteResource.
 */
interface PricingServiceInterface
{
    /**
     * @return array{
     *   currency: string,
     *   total: \Brick\Money\Money,
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
