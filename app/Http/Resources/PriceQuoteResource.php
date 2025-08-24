<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceQuoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $this->resource is for PricingService::quote()
        return [
            'currency'    => $this->resource['currency'],
            'total_minor' => $this->resource['total']->getMinorAmount()->toInt(),
            'total'       => (string) $this->resource['total'],
            'breakdown'   => $this->resource['breakdown'],
        ];
    }
}
