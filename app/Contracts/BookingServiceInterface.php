<?php

namespace App\Contracts;

use App\Domain\ValueObjects\DateRange;
use App\Models\Booking;

interface BookingServiceInterface
{
    /**
     * Create a booking. Validates capacity, pricing and idempotency.
     *
     * @param array{
     *   customer_name:string,
     *   customer_email:string,
     *   vehicle_reg:string,
     *   from_date:string,
     *   to_datetime:string
     * } $dto
     *
     * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
     */
    public function create(array $dto): Booking;

    /**
     * Amend an existing booking (re-check capacity, re-price, bump version).
     *
     * @param array{
     *   from_date:string,
     *   to_datetime:string,
     *   version?:int
     * } $dto
     */
    public function amend(Booking $booking, array $dto): Booking;

    /**
     * Cancel a booking (non-idempotent; throws if already cancelled).
     */
    public function cancel(Booking $booking): void;
}
