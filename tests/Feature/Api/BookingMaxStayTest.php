<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingMaxStayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rejects_bookings_longer_than_the_max(): void
    {
        // from 22nd to 2nd (11 nights) when max=10
        $resp = $this->postJson('/api/v1/bookings', [
            'customer_name'  => 'Alex',
            'customer_email' => 'alex@example.com',
            'vehicle_reg'    => 'AB12 CDE',
            'from_date'      => '2025-08-22',
            'to_datetime'    => '2025-09-02T09:00:00',
        ])->assertStatus(422);

        $resp->assertJsonFragment(['message' => 'The stay may not exceed 10 days.']);
    }

    #[Test]
    public function it_allows_bookings_up_to_the_max(): void
    {
        // exactly 10 nights: 22..31 (checkout 1st)
        $this->postJson('/api/v1/bookings', [
            'customer_name'  => 'Alex',
            'customer_email' => 'alex@example.com',
            'vehicle_reg'    => 'AB12 CDE',
            'from_date'      => '2025-08-22',
            'to_datetime'    => '2025-09-01T09:00:00',
        ])->assertCreated();
    }
}
