<?php

namespace App\Domain\Services;

use App\Domain\ValueObjects\DateRange;
use App\Contracts\PricingServiceInterface;
use App\Contracts\AvailabilityServiceInterface;
use App\Contracts\BookingServiceInterface;
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
        // 1) Normalize dates
        $range = $this->buildRange($dto);

        // 2) The Model will handle email normalization. Calculate normalized reg for query.
        $email = $dto['customer_email'];
        $regNormalizedForSearch = $this->normalizeReg($dto['vehicle_reg']); // This becomes "ABC123"

        // 3) Idempotency fingerprint (same user+car+from+to)
        $fingerprint = $this->fingerprint($email, $regNormalizedForSearch, $range);

        if (Booking::query()->where('request_fingerprint', $fingerprint)->exists()) {
            throw new ConflictHttpException('This booking has already been submitted.');
        }


        // duplicate-active check now matches stored forms
        $sameCarActiveExists = Booking::query()
            ->active()
            ->forEmail($dto['customer_email'])
            ->forReg($dto['vehicle_reg'])
            ->exists();

        if ($sameCarActiveExists) {
            throw new ConflictHttpException(
                'You already have an active booking for this vehicle. Choose a different vehicle or cancel the existing one.'
            );
        }

        // 5) Create atomically
        try {
            return DB::transaction(function () use ($dto, $range, $fingerprint,$regNormalizedForSearch) {
                $this->availability->assertRangeHasSpace($range);
                $quote = $this->pricing->quote($range);

                // The model mutator will handle normalization of email and vehicle_reg
                  $booking = Booking::create([
                    'customer_name'          => $dto['customer_name'],
                    'customer_email'         => $dto['customer_email'], // model still lowercases
                    'vehicle_reg'            => $dto['vehicle_reg'],    // display form
                    'from_date'              => $range->fromDate,
                    'to_datetime'            => $range->toDateTime,
                    'status'                 => BookingStatus::Active,
                    'total_minor'            => $quote['total']->getMinorAmount()->toInt(),
                    'currency'               => $quote['currency'],
                    'request_fingerprint'    => $fingerprint,
    ]);

                $this->syncDays($booking, $range);
                return $booking->refresh();
            });
        } catch (QueryException $e) {
            if (Str::contains($e->getMessage(), 'request_fingerprint')) {
                throw new ConflictHttpException('This booking has already been submitted.');
            }
            throw $e;
        }
    }

    public function amend(Booking $booking, array $dto): Booking
    {
        return DB::transaction(function () use ($booking, $dto) {
            $range = $this->buildRange($dto);

            // capacity, ignoring current booking
            $this->availability->assertRangeHasSpace($range, $booking->id);

            // re-price
            $quote = $this->pricing->quote($range);

            // We do not apply idempotency to amendments
            $booking->update([
                'from_date'   => $range->fromDate,
                'to_datetime' => $range->toDateTime,
                'total_minor' => $quote['total']->getMinorAmount()->toInt(),
                'currency'    => $quote['currency'],
                'version'     => $booking->version + 1,
            ]);

            $this->syncDays($booking, $range);

            return $booking->refresh();
        });
    }

    public function cancel(Booking $booking): void
    {
        if ($booking->status === BookingStatus::Cancelled) {
        throw new ConflictHttpException('This booking is already cancelled.');
    }
        DB::transaction(function () use ($booking) {
            // Clear fingerprint so the *same* payload can be used later
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
        // Uppercase + strip spaces for consistent comparisons
        return Str::upper(preg_replace('/\s+/', '', $reg));
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
        if ($rows) {
            BookingDay::insert($rows);
        }
    }
}
