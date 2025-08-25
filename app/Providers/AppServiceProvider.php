<?php

namespace App\Providers;

use App\Contracts\AvailabilityServiceInterface;
use App\Contracts\BookingServiceInterface;
use App\Contracts\PricingServiceInterface;
use App\Domain\Services\AvailabilityService;
use App\Domain\Services\BookingService;
use App\Domain\Services\PricingService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PricingServiceInterface::class, static function () {
            $cfg = (array) config('pricing', []);

            $currency = strtoupper((string) ($cfg['currency'] ?? 'GBP'));
            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                $currency = 'GBP';
            }

            return new PricingService(
                currency:      $currency,
                rates:         (array) ($cfg['rates'] ?? []),
                summerMonths:  (array) ($cfg['summer_months'] ?? []),
                winterMonths:  (array) ($cfg['winter_months'] ?? []),
                defaultSeason: (string) config('booking.default_season', 'winter'),
                weekendDays:   (array) config('booking.weekend_days', [6, 0]),
            );
        });

        $this->app->singleton(AvailabilityServiceInterface::class, static function () {
            $capacity = config('parking.capacity');

            return new AvailabilityService(
                defaultCapacity: is_int($capacity) ? $capacity : 10
            );
        });

        $this->app->singleton(BookingServiceInterface::class, static function ($app) {
            return new BookingService(
                availability: $app->make(AvailabilityServiceInterface::class),
                pricing:      $app->make(PricingServiceInterface::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
}
