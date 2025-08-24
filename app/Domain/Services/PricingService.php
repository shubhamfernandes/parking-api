<?php

namespace App\Domain\Services;

use App\Domain\ValueObjects\DateRange;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use App\Contracts\PricingServiceInterface;
class PricingService implements PricingServiceInterface
{
    public function __construct(
        public readonly string $currency,
        public readonly array $rates,
        public readonly array $summerMonths,
        public readonly array $winterMonths,
    ) {}

    public function quote(DateRange $range): array
    {
        $items = [];
        $totalMinor = 0;

        foreach ($range->eachOccupiedDay() as $dateStr) {
            $d = CarbonImmutable::parse($dateStr);
            $isWeekend = (int) $d->isWeekend(); // 1 or 0
            $season = $this->seasonForMonth((int) $d->month);

            $key = $isWeekend ? 'weekend' : 'weekday';
            $minor = $this->rates[$season][$key];

            $items[] = [
                'date' => $dateStr,
                'season' => $season,
                'day_type' => $key,
                'amount_minor' => $minor,
            ];
            $totalMinor += $minor;
        }

        return [
            'currency' => $this->currency,
            'total' => Money::ofMinor($totalMinor, $this->currency),
            'breakdown' => $items,
        ];
    }

    private function seasonForMonth(int $m): string
    {
        if (in_array($m, $this->summerMonths, true)) return 'summer';
        if (in_array($m, $this->winterMonths, true)) return 'winter';
        // choose a default; here map to closest band (winter by default)
        return 'winter';
    }
}
