<?php


namespace Database\Factories;

use App\Models\BookingDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingDayFactory extends Factory
{
    protected $model = BookingDay::class;

    public function definition(): array
    {
        return [
            'booking_id' => null,
            'day' => CarbonImmutable::now()->addDay()->toDateString(),
        ];
    }
}
