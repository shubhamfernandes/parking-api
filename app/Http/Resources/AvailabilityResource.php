<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'range'               => $this->resource['range'],
            'all_days_have_space' => (bool) $this->resource['all_days_have_space'],
            'per_day'             => collect($this->resource['per_day'])->values(),
        ];
    }
}
