<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BookingServiceInterface;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookingStoreRequest;
use App\Http\Requests\BookingUpdateRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;


class BookingController extends Controller
{
 public function store(BookingStoreRequest $request, BookingServiceInterface $service)
{
    $booking = $service->create($request->validated());

    return BookingResource::make($booking)
        ->response()
        ->setStatusCode(201);
}

public function show(Booking $booking)
{
    return BookingResource::make($booking);
}

public function update(BookingUpdateRequest $request, Booking $booking, BookingServiceInterface $service)
{
    abort_if($booking->status !== BookingStatus::Active, 422, 'Cannot amend a cancelled booking.');

    $booking = $service->amend($booking, $request->validated());

    return BookingResource::make($booking);
}

public function destroy(Booking $booking, BookingServiceInterface $service)
{
    $service->cancel($booking);

     return BookingResource::make($booking->refresh())
        ->additional([
            'message'   => 'Booking cancelled',
            'reference' => $booking->reference,
        ]);
}

}
