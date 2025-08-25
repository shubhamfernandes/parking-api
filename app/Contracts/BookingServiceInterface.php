<?php

namespace App\Contracts;

use App\Models\Booking;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

interface BookingServiceInterface
{
    /**
     * Create a booking. Validates capacity, pricing and idempotency.
     *
     * @param array{
     *   customer_name: string,
     *   customer_email: string,
     *   vehicle_reg: string,
     *   from_date: string,
     *   to_datetime: string
     * } $dto
     *
     * @throws ConflictHttpException If capacity is exceeded or idempotency violated.
     */
    public function create(array $dto): Booking;

    /**
     * Amend an existing booking (re-check capacity, re-price, bump version).
     *
     * @param Booking $booking
     * @param array{
     *   from_date: string,
     *   to_datetime: string,
     *   version?: int
     * } $dto
     *
     * @throws ConflictHttpException If capacity is exceeded or version conflict occurs.
     */
    public function amend(Booking $booking, array $dto): Booking;

    /**
     * Cancel a booking (non-idempotent; throws if already cancelled).
     *
     * @throws ConflictHttpException If the booking is already cancelled.
     */
    public function cancel(Booking $booking): void;
}
