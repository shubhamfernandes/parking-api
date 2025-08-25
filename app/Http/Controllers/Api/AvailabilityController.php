<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AvailabilityServiceInterface;
use App\Domain\ValueObjects\DateRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteAvailabilityRequest;
use App\Http\Resources\AvailabilityResource;

class AvailabilityController extends Controller
{
    public function calendar(
        QuoteAvailabilityRequest $request,
        AvailabilityServiceInterface $availability
    ): AvailabilityResource {
        $range = new DateRange(
            fromDate:   $request->date('from_date')->toImmutable()->startOfDay(),
            toDateTime: $request->date('to_datetime')->toImmutable()
        );

        $calendar = $availability->calendar($range);
        $allAvailable = $calendar->every(fn ($d) => $d['available'] > 0);

        return new AvailabilityResource([
            'range' => $request->only('from_date', 'to_datetime'),
            'all_days_have_space' => $allAvailable,
            'per_day' => $calendar,
        ]);
    }
}
