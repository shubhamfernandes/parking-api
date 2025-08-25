<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Capacity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AvailabilityEndpointTest extends TestCase
{
    use RefreshDatabase; // (optional if already on base TestCase)

    #[Test]
    public function it_returns_per_day_capacity_and_booked_counts(): void
    {
        $from = CarbonImmutable::parse('2025-08-22')->startOfDay();

        Capacity::factory()->create(['day' => '2025-08-23', 'capacity' => 8]);

        $b1 = Booking::factory()->create([
            'from_date'   => $from,
            'to_datetime' => $from->addDays(3)->setTime(9, 0),
        ]);
        $b2 = Booking::factory()->create([
            'from_date'   => $from,
            'to_datetime' => $from->addDays(3)->setTime(8, 0),
        ]);

        foreach (['2025-08-22', '2025-08-23', '2025-08-24'] as $day) {
            BookingDay::factory()->create(['booking_id' => $b1->id, 'day' => $day]);
            BookingDay::factory()->create(['booking_id' => $b2->id, 'day' => $day]);
        }

        $data = $this->getJson('/api/v1/availability?from_date=2025-08-22&to_datetime=2025-08-25T09:00:00')
            ->assertOk()
            ->assertJsonStructure([
                'range'  => ['from_date', 'to_datetime'],
                'per_day'=> [['date', 'capacity', 'booked', 'available']],
            ])
            ->json();

        $this->assertSame('2025-08-22', $data['range']['from_date']);
        $this->assertMatchesRegularExpression(
            '/^2025-08-25T09:00:00(?:Z|\+00:00)?$/',
            $data['range']['to_datetime']
        );

        $perDay = collect($data['per_day'])->keyBy('date');

        $this->assertSame(10, $perDay['2025-08-22']['capacity']);
        $this->assertSame(2,  $perDay['2025-08-22']['booked']);
        $this->assertSame(8,  $perDay['2025-08-22']['available']);

        $this->assertSame(8,  $perDay['2025-08-23']['capacity']);
        $this->assertSame(2,  $perDay['2025-08-23']['booked']);
        $this->assertSame(6,  $perDay['2025-08-23']['available']);

        $this->assertSame(10, $perDay['2025-08-24']['capacity']);
        $this->assertSame(2,  $perDay['2025-08-24']['booked']);
        $this->assertSame(8,  $perDay['2025-08-24']['available']);

        // checkout day excluded
        $this->assertFalse($perDay->has('2025-08-25'));
    }

    #[Test]
    public function it_excludes_check_out_day_from_availability(): void
    {
        $from = CarbonImmutable::parse('2025-08-22');
        $to   = CarbonImmutable::parse('2025-08-23T14:00:00');

        $booking = Booking::factory()->create([
            'from_date'   => $from,
            'to_datetime' => $to,
        ]);

        BookingDay::factory()->create(['booking_id' => $booking->id, 'day' => '2025-08-22']);

        $data = $this->getJson('/api/v1/availability?from_date=2025-08-22&to_datetime=2025-08-23T14:00:00')
            ->assertOk()
            ->json();

        $perDay = collect($data['per_day'])->keyBy('date');

        $this->assertCount(1, $data['per_day']);
        $this->assertSame(1, $perDay['2025-08-22']['booked']);
        $this->assertSame(9, $perDay['2025-08-22']['available']);
        $this->assertFalse($perDay->has('2025-08-23'));
    }

    #[Test]
    public function it_handles_zero_capacity_days(): void
    {
        Capacity::factory()->create(['day' => '2025-08-23', 'capacity' => 0]);
        Capacity::factory()->create(['day' => '2025-08-24', 'capacity' => 5]);

        $data = $this->getJson('/api/v1/availability?from_date=2025-08-23&to_datetime=2025-08-25T09:00:00')
            ->assertOk()
            ->json();

        $perDay = collect($data['per_day'])->keyBy('date');

        $this->assertSame(0, $perDay['2025-08-23']['capacity']);
        $this->assertSame(0, $perDay['2025-08-23']['booked']);
        $this->assertSame(0, $perDay['2025-08-23']['available']);

        $this->assertSame(5, $perDay['2025-08-24']['capacity']);
        $this->assertSame(0, $perDay['2025-08-24']['booked']);
        $this->assertSame(5, $perDay['2025-08-24']['available']);
    }

    #[Test]
    public function it_handles_fully_booked_days(): void
    {
        Capacity::factory()->create(['day' => '2025-08-23', 'capacity' => 2]);

        $b1 = Booking::factory()->create();
        $b2 = Booking::factory()->create();

        BookingDay::factory()->create(['booking_id' => $b1->id, 'day' => '2025-08-23']);
        BookingDay::factory()->create(['booking_id' => $b2->id, 'day' => '2025-08-23']);

        $data = $this->getJson('/api/v1/availability?from_date=2025-08-23&to_datetime=2025-08-24T09:00:00')
            ->assertOk()
            ->json();

        $perDay = collect($data['per_day'])->keyBy('date');

        $this->assertSame(2, $perDay['2025-08-23']['capacity']);
        $this->assertSame(2, $perDay['2025-08-23']['booked']);
        $this->assertSame(0, $perDay['2025-08-23']['available']);
    }

    #[Test]
    public function it_validates_required_date_parameters(): void
    {
        $this->getJson('/api/v1/availability')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_date', 'to_datetime']);

        $this->getJson('/api/v1/availability?from_date=invalid&to_datetime=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_date', 'to_datetime']);
    }

    #[Test]
    public function it_validates_date_range(): void
    {
        $this->getJson('/api/v1/availability?from_date=2025-08-25&to_datetime=2025-08-24T09:00:00')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_datetime']);
    }

    #[Test]
    public function it_handles_empty_date_ranges(): void
    {
        $data = $this->getJson('/api/v1/availability?from_date=2025-08-22&to_datetime=2025-08-22T09:00:00')
            ->assertOk()
            ->json();

        $this->assertSame('2025-08-22', $data['range']['from_date']);
        $this->assertMatchesRegularExpression(
            '/^2025-08-22T09:00:00(?:Z|\+00:00)?$/',
            $data['range']['to_datetime']
        );
        $this->assertEmpty($data['per_day']);
    }

    #[Test]
    public function it_returns_correct_availability_with_multiple_bookings(): void
    {
        Capacity::factory()->create(['day' => '2025-08-23', 'capacity' => 5]);

        $b1 = Booking::factory()->create();
        $b2 = Booking::factory()->create();
        $b3 = Booking::factory()->create();

        BookingDay::factory()->create(['booking_id' => $b1->id, 'day' => '2025-08-23']);
        BookingDay::factory()->create(['booking_id' => $b2->id, 'day' => '2025-08-23']);
        BookingDay::factory()->create(['booking_id' => $b2->id, 'day' => '2025-08-24']);
        BookingDay::factory()->create(['booking_id' => $b3->id, 'day' => '2025-08-24']);

        $data = $this->getJson('/api/v1/availability?from_date=2025-08-23&to_datetime=2025-08-25T09:00:00')
            ->assertOk()
            ->json();

        $perDay = collect($data['per_day'])->keyBy('date');

        $this->assertSame(5,  $perDay['2025-08-23']['capacity']);
        $this->assertSame(2,  $perDay['2025-08-23']['booked']);
        $this->assertSame(3,  $perDay['2025-08-23']['available']);

        $this->assertSame(10, $perDay['2025-08-24']['capacity']); // default
        $this->assertSame(2,  $perDay['2025-08-24']['booked']);   // booking2 + booking3
        $this->assertSame(8,  $perDay['2025-08-24']['available']);
    }

    #[Test]
    public function it_handles_no_capacity_records_uses_default(): void
    {
        $data = $this->getJson('/api/v1/availability?from_date=2025-08-23&to_datetime=2025-08-24T09:00:00')
            ->assertOk()
            ->json();

        $perDay = collect($data['per_day'])->keyBy('date');

        $this->assertSame(10, $perDay['2025-08-23']['capacity']);
        $this->assertSame(0,  $perDay['2025-08-23']['booked']);
        $this->assertSame(10, $perDay['2025-08-23']['available']);
    }

    #[Test]
    public function all_days_have_space_is_true_when_every_day_has_capacity(): void
    {
        $resp = $this->getJson('/api/v1/availability?from_date=2025-08-22&to_datetime=2025-08-24T09:00:00')
            ->assertOk()
            ->json();

        $this->assertTrue((bool) $resp['all_days_have_space']);
    }

    #[Test]
    public function all_days_have_space_is_false_when_any_day_is_full(): void
    {
        // Cap = 1 and one booking on 2025-08-22 makes that day full
        \App\Models\Capacity::factory()->create(['day' => '2025-08-22', 'capacity' => 1]);

        $this->postJson('/api/v1/bookings', [
            'customer_name'  => 'Full Day',
            'customer_email' => 'full@example.com',
            'vehicle_reg'    => 'FD11 FULL',
            'from_date'      => '2025-08-22',
            'to_datetime'    => '2025-08-23T09:00:00',
        ])->assertCreated();

        $resp = $this->getJson('/api/v1/availability?from_date=2025-08-22&to_datetime=2025-08-24T09:00:00')
            ->assertOk()
            ->json();

        $this->assertFalse((bool) $resp['all_days_have_space']);
    }
}
