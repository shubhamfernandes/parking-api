<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $from = CarbonImmutable::now()->addDay()->startOfDay();           // tomorrow
        $to   = $from->addDays(2)->setTime(9, 30);                         // +2 days 09:30

        return [
            'reference'      => 'BK-'.strtoupper((string) str()->ulid()),
            'customer_name'  => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
            'vehicle_reg'    => strtoupper($this->faker->bothify('??## ???')),
            'from_date'      => $from,
            'to_datetime'    => $to,
            'total_minor'    => 0,
            'currency'       => 'GBP',
            'status'         => BookingStatus::Active,
            'version'        => 1,
        ];
    }
}
