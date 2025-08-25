<?php

namespace Tests\Feature\Api;

use App\Domain\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PricingEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();


        // Ensure config matches what PricingService expects
        config()->set('pricing.currency', 'GBP');
        config()->set('pricing.rates', [
            'summer' => ['weekday' => 1500, 'weekend' => 2000],
            'winter' => ['weekday' => 1200, 'weekend' => 1600],
        ]);
        config()->set('pricing.summer_months', [6, 7, 8]);
        config()->set('pricing.winter_months', [12, 1, 2]);

        // Bind the service so the container resolves constructor args
        $this->app->bind(PricingService::class, function () {
            return new PricingService(
                currency: config('pricing.currency'),
                rates: config('pricing.rates'),
                summerMonths: config('pricing.summer_months'),
                winterMonths: config('pricing.winter_months'),
            );
        });
    }

    #[Test]
    public function it_validates_required_parameters(): void
    {
        $this->getJson('/api/v1/price')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_date', 'to_datetime']);

        $this->getJson('/api/v1/price?from_date=bad&to_datetime=also-bad')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_date', 'to_datetime']);
    }

    #[Test]
    public function it_validates_date_range_order(): void
    {
        $this->getJson('/api/v1/price?from_date=2025-08-23&to_datetime=2025-08-22T09:00:00')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_datetime']);
    }

    #[Test]
    public function it_prices_summer_weekdays_correctly(): void
    {
        // Tue–Thu in August (summer); occupied: 26,27 (checkout 28th excluded)
        $resp = $this->getJson('/api/v1/price?from_date=2025-08-26&to_datetime=2025-08-28T07:30:00')
            ->assertOk()
            ->json();

        $this->assertSame('GBP', $resp['currency']);
        $this->assertSame(3000, $resp['total_minor']);      // 2 * 1500
        $this->assertSame('GBP 30.00', $resp['total']);

        $dates = collect($resp['breakdown'])->pluck('date')->all();
        $this->assertSame(['2025-08-26', '2025-08-27'], $dates);

        foreach ($resp['breakdown'] as $row) {
            $this->assertSame('summer', $row['season']);
            $this->assertSame('weekday', $row['day_type']);
            $this->assertSame(1500, $row['amount_minor']);
        }

        // total_minor equals sum of breakdown
        $this->assertSame(
            $resp['total_minor'],
            collect($resp['breakdown'])->sum('amount_minor')
        );
    }

    #[Test]
    public function it_applies_summer_weekend_rates_on_sat_sun(): void
    {
        // Fri–Mon in August; bill Fri (weekday), Sat/Sun (weekend)
        $resp = $this->getJson('/api/v1/price?from_date=2025-08-22&to_datetime=2025-08-25T09:00:00')
            ->assertOk()
            ->json();

        // 1500 + 2000 + 2000 = 5500
        $this->assertSame(5500, $resp['total_minor']);
        $this->assertSame('GBP 55.00', $resp['total']);

        $bd = collect($resp['breakdown'])->keyBy('date');

        $this->assertSame('summer', $bd['2025-08-22']['season']);
        $this->assertSame('weekday', $bd['2025-08-22']['day_type']);
        $this->assertSame(1500, $bd['2025-08-22']['amount_minor']);

        $this->assertSame('weekend', $bd['2025-08-23']['day_type']);
        $this->assertSame(2000, $bd['2025-08-23']['amount_minor']);

        $this->assertSame('weekend', $bd['2025-08-24']['day_type']);
        $this->assertSame(2000, $bd['2025-08-24']['amount_minor']);

        // Checkout 25th not billed
        $this->assertFalse($bd->has('2025-08-25'));

        $this->assertSame(
            $resp['total_minor'],
            collect($resp['breakdown'])->sum('amount_minor')
        );
    }

    #[Test]
    public function it_prices_winter_weekdays_correctly(): void
    {
        // Tue–Thu in January (winter); occupied: 14,15
        $resp = $this->getJson('/api/v1/price?from_date=2026-01-14&to_datetime=2026-01-16T10:00:00')
            ->assertOk()
            ->json();

        // 2 * 1200 = 2400
        $this->assertSame(2400, $resp['total_minor']);
        $this->assertSame('GBP 24.00', $resp['total']);

        foreach ($resp['breakdown'] as $row) {
            $this->assertSame('winter', $row['season']);
            $this->assertSame('weekday', $row['day_type']);
            $this->assertSame(1200, $row['amount_minor']);
        }

        $this->assertSame(
            $resp['total_minor'],
            collect($resp['breakdown'])->sum('amount_minor')
        );
    }

    #[Test]
    public function it_mixes_summer_and_winter_across_season_boundary(): void
    {
        // Aug 31 (summer Sunday) -> Sep 1 checkout (not billed). Only Aug 31 charged.
        $resp = $this->getJson('/api/v1/price?from_date=2025-08-31&to_datetime=2025-09-01T09:00:00')
            ->assertOk()
            ->json();

        $this->assertSame(2000, $resp['total_minor']);      // summer weekend
        $this->assertSame('GBP 20.00', $resp['total']);
        $this->assertCount(1, $resp['breakdown']);

        $row = $resp['breakdown'][0];
        $this->assertSame('2025-08-31', $row['date']);
        $this->assertSame('summer', $row['season']);
        $this->assertSame('weekend', $row['day_type']);
        $this->assertSame(2000, $row['amount_minor']);
    }

    #[Test]
    public function it_handles_zero_night_ranges_as_zero_total(): void
    {
        // Same-day pickup — no occupied days
        $resp = $this->getJson('/api/v1/price?from_date=2025-08-22&to_datetime=2025-08-22T09:00:00')
            ->assertOk()
            ->json();

        $this->assertSame(0, $resp['total_minor']);
        $this->assertSame('GBP 0.00', $resp['total']);
        $this->assertSame([], $resp['breakdown']);
    }
}
