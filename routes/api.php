<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\BookingController;

Route::prefix('v1')
    ->as('v1.')
    ->middleware(['throttle:60,1','api'])
    ->group(function () {

        // Availability Calendar
        Route::get('availability', [AvailabilityController::class, 'calendar'])
            ->name('availability.calendar'); // GET /api/v1/availability

        // Pricing Quote
        Route::get('price', [PricingController::class, 'quote'])
            ->name('pricing.quote');         // GET /api/v1/price

        // Bookings CRUD (store, show, update, cancel)
        Route::apiResource('bookings', BookingController::class)
            ->only(['store','show','update','destroy']); // /api/v1/bookings[/{id}]
    });

    Route::fallback(fn () => response()->json([
    'error' => ['type' => 'not_found', 'message' => 'Route not found.']
], 404));
