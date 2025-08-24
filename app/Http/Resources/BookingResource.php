<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $money = $this->total; // accessor returns Brick\Money\Money

        return [
            'id'             => (string) $this->id,
            'reference'      => $this->reference,
            'status'         => $this->status->value,

            // include customer data for tests
            'customer_name'  => $this->customer_name,
            'customer_email' => $this->customer_email,
            'vehicle_reg'    => $this->vehicle_reg,

            'from_date'      => $this->from_date?->toDateString(),
            'to_datetime'    => $this->to_datetime?->toIso8601String(),

            'total_minor'    => $money->getMinorAmount()->toInt(),
            'total'          => (string) $money,
            'currency'       => $this->currency,

            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),

            'days' => $this->whenLoaded('days', fn () =>
                $this->days->map(fn ($d) => $d->day->toDateString())
            ),
        ];
    }
}
