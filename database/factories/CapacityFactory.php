<?php

namespace Database\Factories;

use App\Models\Capacity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
class CapacityFactory extends Factory
{
    protected $model = Capacity::class;

    public function definition(): array
    {
        return [
            'day' => CarbonImmutable::now()->addDay()->toDateString(),
            'capacity' => 10,
        ];
    }
}
