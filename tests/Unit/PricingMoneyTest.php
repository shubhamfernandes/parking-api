<?php

namespace Tests\Unit;

use App\Domain\Services\PricingService;
use App\Domain\ValueObjects\DateRange;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PricingMoneyTest extends TestCase
{
    private function pricing(): PricingService
    {
        return new PricingService(
            currency: 'GBP',
            rates: [
                'summer' => ['weekday' => 1500, 'weekend' => 2000],
                'winter' => ['weekday' => 1200, 'weekend' => 1600],
            ],
            summerMonths: [6,7,8],
            winterMonths: [12,1,2],
        );
    }

    private static function range(string $fromDate, string $toDateTime): DateRange
    {
        return new DateRange(
            CarbonImmutable::parse($fromDate)->startOfDay(),
            CarbonImmutable::parse($toDateTime)
        );
    }

    public static function goldenCases(): array
    {
        return [
            // Summer weekday only (Tue)
            'summer_weekday' => [
                'from' => '2025-07-01', 'to' => '2025-07-02T09:00:00',
                'expectedMinor' => 1500,
            ],
            // Summer weekend (Sat night)
            'summer_weekend' => [
                'from' => '2025-07-05', 'to' => '2025-07-06T09:00:00',
                'expectedMinor' => 2000,
            ],
            // Winter weekday (Tue)
            'winter_weekday' => [
                'from' => '2025-01-07', 'to' => '2025-01-08T09:00:00',
                'expectedMinor' => 1200,
            ],
            // Winter weekend (Sat)
            'winter_weekend' => [
                'from' => '2025-01-04', 'to' => '2025-01-05T09:00:00',
                'expectedMinor' => 1600,
            ],
            // Season boundary (Aug 31 → Sep 2) = Sun (summer weekend) + Mon (default winter weekday)
            'season_boundary' => [
                'from' => '2025-08-31', 'to' => '2025-09-02T09:00:00',
                // 2025-08-31 (Sun, summer weekend=2000) + 2025-09-01 (Mon, default winter weekday=1200) = 3200
                'expectedMinor' => 2000 + 1200,
            ],
        ];
    }

    #[DataProvider('goldenCases')]
    public function test_quote_matches_expected_minor_totals(string $from, string $to, int $expectedMinor): void
    {
        $svc = $this->pricing();
        $quote = $svc->quote(self::range($from, $to));

        // No floats anywhere
        $this->assertIsInt($quote['total']->getMinorAmount()->toInt());
        $this->assertSame('GBP', $quote['currency']);

        // Exact total
        $this->assertSame($expectedMinor, $quote['total']->getMinorAmount()->toInt());

        // Sum of breakdown equals total
        $sum = array_sum(array_column($quote['breakdown'], 'amount_minor'));
        $this->assertSame($expectedMinor, $sum);

        // Brick\Money equality
        $expected = Money::ofMinor($expectedMinor, 'GBP');
        $this->assertTrue($quote['total']->isEqualTo($expected));
    }

    public function test_dst_transition_in_uk_does_not_change_day_count_or_total(): void
    {
        // UK spring forward in 2025: Sun 30 Mar
        // Range: 29 Mar 00:00 → 31 Mar 09:00 should price 29th (Sat) + 30th (Sun) = 2 days
        // March not in summer/winter arrays -> defaults to winter rates in your code.
        $svc = $this->pricing();
        $range = self::range('2025-03-29', '2025-03-31T09:00:00');

        $quote = $svc->quote($range);
        $this->assertCount(2, $quote['breakdown']); // 29th & 30th

        // Both are weekend days → winter weekend rate each = 1600 + 1600
        $this->assertSame(1600 + 1600, $quote['total']->getMinorAmount()->toInt());
    }
}
