<?php

namespace App\Http\Controllers\Api;

use App\Domain\ValueObjects\DateRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteAvailabilityRequest;
use App\Http\Resources\PriceQuoteResource;
use App\Contracts\PricingServiceInterface;

class PricingController extends Controller
{
            public function quote(QuoteAvailabilityRequest $request, PricingServiceInterface $pricing)
             {
            $range = new DateRange(
                fromDate:   $request->date('from_date')->toImmutable()->startOfDay(),
                toDateTime: $request->date('to_datetime')->toImmutable()
            );

            return new PriceQuoteResource($pricing->quote($range));
        }

}
