<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BookingServiceInterface;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookingStoreRequest;
use App\Http\Requests\BookingUpdateRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function store(BookingStoreRequest $request, BookingServiceInterface $service): JsonResponse
    {
        $booking = $service->create($request->validated());

        return BookingResource::make($booking)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Booking $booking): BookingResource
    {
        return BookingResource::make($booking);
    }

    public function update(
        BookingUpdateRequest $request,
        Booking $booking,
        BookingServiceInterface $service
    ): BookingResource {
        abort_if($booking->status !== BookingStatus::Active, 422, 'Cannot amend a cancelled booking.');

        $updated = $service->amend($booking, $request->validated());

        return BookingResource::make($updated);
    }

    public function destroy(Booking $booking, BookingServiceInterface $service): BookingResource
    {
        $service->cancel($booking);

        return BookingResource::make($booking->refresh())
            ->additional([
                'message'   => 'Booking cancelled',
                'reference' => $booking->reference,
            ]);
    }
}
