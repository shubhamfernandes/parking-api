<?php

namespace App\Domain\Services;

use App\Contracts\AvailabilityServiceInterface;
use App\Contracts\BookingServiceInterface;
use App\Contracts\PricingServiceInterface;
use App\Domain\ValueObjects\DateRange;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingService implements BookingServiceInterface
{
    public function __construct(
        private readonly AvailabilityServiceInterface $availability,
        private readonly PricingServiceInterface $pricing,
    ) {}

    public function create(array $dto): Booking
    {
        // 1) Normalize request window
        $range = $this->buildRange($dto);

        // 2) Idempotency fingerprint (email + normalized reg + exact window)
        $fingerprint = $this->fingerprint(
            $dto['customer_email'],
            $this->normalizeReg($dto['vehicle_reg']),
            $range
        );

        // 3) Idempotency pre-check (avoid work if same payload already active)
        if ($existing = Booking::query()->where('request_fingerprint', $fingerprint)->first()) {
            if ($existing->status === BookingStatus::Active) {
                throw new ConflictHttpException('This booking has already been submitted.');
            }
            // cancelled → allow same payload again
        }

        // 4) Duplicate rule (vehicle-only, overlap-aware)
        if (
            Booking::query()
                ->activeOverlappingVehicle($dto['vehicle_reg'], $range->fromDate, $range->toDateTime)
                ->exists()
        ) {
            throw new ConflictHttpException(
                'This vehicle already has an active booking that overlaps with these dates. ' .
                'Choose a different vehicle or cancel the existing one.'
            );
        }

        // 5) Create atomically
        try {
            $booking = DB::transaction(function () use ($dto, $range, $fingerprint) {
                $this->availability->assertRangeHasSpace($range);

                $quote = $this->pricing->quote($range);

                $booking = Booking::create([
                    'customer_name'       => $dto['customer_name'],
                    'customer_email'      => $dto['customer_email'],
                    'vehicle_reg'         => $dto['vehicle_reg'],
                    'from_date'           => $range->fromDate,
                    'to_datetime'         => $range->toDateTime,
                    'status'              => BookingStatus::Active,
                    'total_minor'         => $quote['total']->getMinorAmount()->toInt(),
                    'currency'            => $quote['currency'],
                    'request_fingerprint' => $fingerprint,
                ]);

                $this->syncDays($booking, $range);

                return $booking;
            });

            // Return a definitely fresh instance
            return $booking->fresh();
        } catch (QueryException $e) {
            // MySQL duplicate key
            if (($e->errorInfo[1] ?? null) == 1062) {
                throw new ConflictHttpException('This booking has already been submitted.');
            }
            throw $e;
        }
    }

    public function amend(Booking $booking, array $dto): Booking
    {
        return DB::transaction(function () use ($booking, $dto) {
            // support changing the vehicle too (optional)
            $targetReg = $dto['vehicle_reg'] ?? $booking->vehicle_reg;

            // build the new window
            $range = $this->buildRange($dto);

            // capacity check, ignoring the current booking’s rows
            $this->availability->assertRangeHasSpace($range, $booking->id);

            // prevent overlap with ANY other active booking for the same vehicle (email ignored)
            $overlapWithOther = Booking::query()
                ->activeOverlappingVehicle($targetReg, $range->fromDate, $range->toDateTime)
                ->whereKeyNot($booking->id)
                ->exists();

            if ($overlapWithOther) {
                throw new ConflictHttpException('This amendment would overlap another booking for this vehicle.');
            }

            // re-price
            $quote = $this->pricing->quote($range);

            // persist changes (including optional vehicle + contact changes)
            $booking->update([
                'customer_name'  => $dto['customer_name']  ?? $booking->customer_name,
                'customer_email' => $dto['customer_email'] ?? $booking->customer_email,
                'vehicle_reg'    => $targetReg,
                'from_date'      => $range->fromDate,
                'to_datetime'    => $range->toDateTime,
                'total_minor'    => $quote['total']->getMinorAmount()->toInt(),
                'currency'       => $quote['currency'],
                'version'        => $booking->version + 1, // bump version on amend
            ]);

            // resync per-day rows
            $this->syncDays($booking, $range);

            // return a definitely fresh instance so your resource sees new values
            return $booking->fresh();
        });
    }

    public function cancel(Booking $booking): void
    {
        if ($booking->status === BookingStatus::Cancelled) {
            throw new ConflictHttpException('This booking is already cancelled.');
        }

        DB::transaction(function () use ($booking) {
            $booking->update([
                'status'              => BookingStatus::Cancelled,
                'request_fingerprint' => null,
            ]);
        });
    }

    /* ------------ helpers ------------ */

    private function buildRange(array $dto): DateRange
    {
        return new DateRange(
            CarbonImmutable::parse($dto['from_date'])->startOfDay(),
            CarbonImmutable::parse($dto['to_datetime'])
        );
    }

    private function normalizeReg(string $reg): string
    {
        // remove all whitespace, uppercase (keeps letters/numbers/symbols intact)
        /** @var string $normalized */
        $normalized = preg_replace('/\s+/', '', $reg) ?? $reg;

        return Str::upper($normalized);
    }

    private function fingerprint(string $email, string $regNormalized, DateRange $range): string
    {
        $emailNormalized = strtolower(trim($email));

        return hash('sha256', implode('|', [
            $emailNormalized,
            $regNormalized,
            $range->fromDate->toDateString(),
            $range->toDateTime->toIso8601String(),
        ]));
    }

    private function syncDays(Booking $booking, DateRange $range): void
    {
        $booking->days()->delete();

        $rows = [];
        foreach ($range->eachOccupiedDay() as $d) {
            $rows[] = ['booking_id' => $booking->id, 'day' => $d];
        }

        if ($rows !== []) {
            BookingDay::insert($rows);
        }
    }
}
