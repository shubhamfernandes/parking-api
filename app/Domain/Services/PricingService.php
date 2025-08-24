<?php

namespace App\Domain\Services;

use App\Domain\ValueObjects\DateRange;
use App\Contracts\PricingServiceInterface;
use Brick\Money\Money;
use Carbon\CarbonImmutable;

class PricingService implements PricingServiceInterface
{
    public function __construct(
        public readonly string $currency,
        public readonly array $rates,
        public readonly array $summerMonths,
        public readonly array $winterMonths,
        public readonly string $defaultSeason = 'winter', // used for shoulder months
        public readonly array $weekendDays = [0, 6],      // 0=Sun, 6=Sat (Carbon dayOfWeek)
    ) {}

    public function quote(DateRange $range): array
    {
        $items = [];
        $totalMinor = 0;

        foreach ($range->eachOccupiedDay() as $dateStr) {
            $d       = CarbonImmutable::parse($dateStr);
            $season  = $this->seasonForMonth((int) $d->month);
            $dayType = $this->isConfiguredWeekend($d) ? 'weekend' : 'weekday';

            $minor = $this->rates[$season][$dayType];

            $items[] = [
                'date'         => $dateStr,
                'season'       => $season,
                'day_type'     => $dayType,
                'amount_minor' => $minor,
            ];
            $totalMinor += $minor;
        }

        return [
            'currency'  => $this->currency,
            'total'     => Money::ofMinor($totalMinor, $this->currency),
            'breakdown' => $items,
        ];
    }

    private function seasonForMonth(int $m): string
    {
        if (in_array($m, $this->summerMonths, true)) return 'summer';
        if (in_array($m, $this->winterMonths, true)) return 'winter';
        return $this->defaultSeason; // configurable fallback for shoulder months
    }

    private function isConfiguredWeekend(CarbonImmutable $d): bool
    {
        // Carbon: 0=Sun ... 6=Sat
        return in_array((int) $d->dayOfWeek, $this->weekendDays, true);
    }
}
