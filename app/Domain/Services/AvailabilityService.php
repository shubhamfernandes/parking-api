<?php

namespace App\Domain\Services;

use App\Domain\ValueObjects\DateRange;
use App\Contracts\AvailabilityServiceInterface;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Capacity;
use Illuminate\Support\Collection;
use Carbon\CarbonInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AvailabilityService implements AvailabilityServiceInterface
{
    public function __construct(public readonly int $defaultCapacity) {}

    /** Per-day availability for a date range (read-only). */
    public function calendar(DateRange $range): Collection
    {
        // 1) Normalize to plain strings 'YYYY-MM-DD'
        $days = collect(iterator_to_array($range->eachOccupiedDay()))
            ->map(fn ($d) => $d instanceof CarbonInterface ? $d->toDateString() : (string) $d)
            ->values(); // e.g. ['2025-08-22', '2025-08-23', ...]

        // 2) Capacity overrides keyed by 'YYYY-MM-DD'
        $caps = Capacity::query()
            ->whereIn('day', $days)
            ->get()
            ->keyBy(fn ($c) => $c->day instanceof CarbonInterface ? $c->day->toDateString() : (string) $c->day);

        // 3) Count active bookings per day, key by 'YYYY-MM-DD'
        $counts = BookingDay::query()
            ->whereIn('booking_days.day', $days)
            ->whereIn('booking_id', Booking::active()->select('id'))
            ->selectRaw('booking_days.day as d, COUNT(*) as booked')
            ->groupBy('booking_days.day')
            ->get()
            ->mapWithKeys(fn ($row) => [
                ($row->d instanceof CarbonInterface ? $row->d->toDateString() : (string) $row->d)
                    => (int) $row->booked
            ]);

        // 4) Build response with matching keys
        return $days->map(function (string $d) use ($caps, $counts) {
            $capacity = (int) ($caps->get($d)?->capacity ?? $this->defaultCapacity);
            $booked   = (int) ($counts[$d] ?? 0);

            return [
                'date'      => $d,
                'capacity'  => $capacity,
                'booked'    => $booked,
                'available' => max(0, $capacity - $booked),
            ];
        });
    }


    public function assertRangeHasSpace(DateRange $range, ?string $ignoreBookingId = null): void
    {
        // Normalize + deterministic order to reduce deadlocks
        $days = collect(iterator_to_array($range->eachOccupiedDay()))
            ->map(fn ($d) => $d instanceof CarbonInterface ? $d->toDateString() : (string) $d)
            ->sort()
            ->values();

        foreach ($days as $dateStr) {
            // Ensure capacity row exists, then lock it
            $cap = Capacity::firstOrCreate(['day' => $dateStr], ['capacity' => $this->defaultCapacity]);
            $cap = Capacity::whereKey($cap->getKey())->lockForUpdate()->first();

            // Lock matching booking_days while counting
            $booked = BookingDay::query()
                ->where('booking_days.day', $dateStr)
                ->when($ignoreBookingId, fn($q) => $q->where('booking_id', '!=', $ignoreBookingId))
                ->whereIn('booking_id', Booking::active()->select('id'))
                ->lockForUpdate()
                ->count();

            if ($booked >= (int) $cap->capacity) {
                throw new ConflictHttpException("No spaces available on {$dateStr}");
            }
        }
    }
}
