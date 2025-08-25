<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name'  => 'Alex Doe',
            'customer_email' => 'alex@example.com',
            'vehicle_reg'    => 'AB12 CDE',
            'from_date'      => '2025-08-22',
            'to_datetime'    => '2025-08-23T09:30:00',
        ], $overrides);
    }

    #[Test]
    public function it_creates_a_booking_and_returns_a_resource(): void
    {
        $resp = $this->postJson('/api/v1/bookings', $this->validPayload())
            ->assertCreated()
            ->assertJsonStructure([
                'id', 'reference', 'status',
                'from_date', 'to_datetime',
                'total_minor', 'total', 'currency',
                'created_at', 'updated_at',
            ])
            ->json();

        $this->assertSame('active', $resp['status']);
        $this->assertSame('2025-08-22', $resp['from_date']);
        // accept any ISO8601 offset (Z, +00:00, +01:00, etc.)
        $this->assertMatchesRegularExpression(
            '/^2025-08-23T09:30:00(?:Z|[+\-]\d{2}:\d{2})?$/',
            $resp['to_datetime']
        );

        // Pricing: summer weekday (Fri) = 1500
        $this->assertSame(1500, $resp['total_minor']);
        $this->assertSame('GBP', $resp['currency']);
        $this->assertSame('GBP 15.00', $resp['total']);
    }

    #[Test]
    public function it_prevents_overlapping_active_booking_for_same_vehicle(): void
    {
        // First booking: 2025-08-22 → 2025-08-23T09:30:00
        $this->postJson('/api/v1/bookings', $this->validPayload())->assertCreated();

        // Second booking starts 2025-08-23 00:00, which OVERLAPS the first (ends 09:30)
        $this->postJson('/api/v1/bookings', $this->validPayload([
            'from_date'   => '2025-08-23',              // 00:00 on same day => overlap
            'to_datetime' => '2025-08-24T09:00:00',
        ]))
            ->assertStatus(409)
            ->assertJsonFragment([
                'message' => 'This vehicle already has an active booking that overlaps with these dates. Choose a different vehicle or cancel the existing one.',
            ]);
    }

    #[Test]
    public function it_allows_same_user_to_book_a_different_car(): void
    {
        $this->postJson('/api/v1/bookings', $this->validPayload())->assertCreated();

        $second = $this->postJson('/api/v1/bookings', $this->validPayload([
            'vehicle_reg' => 'ZX99 ZZZ',
        ]))
            ->assertCreated()
            ->json();

        $this->assertSame('active', $second['status']);
        $this->assertArrayHasKey('id', $second);        // resource doesn't expose vehicle_reg
        $this->assertStringStartsWith('BK-', $second['reference']);
    }

    #[Test]
    public function it_amends_a_booking_updates_total_and_version_and_days(): void
    {
        $created = $this->postJson('/api/v1/bookings', $this->validPayload())
            ->assertCreated()
            ->json();

        $bookingId = $created['id'];

        // Your BookingUpdateRequest requires customer fields — include them
        $amended = $this->putJson("/api/v1/bookings/{$bookingId}", [
            'customer_name'  => 'Alex Doe',
            'customer_email' => 'alex@example.com',
            'vehicle_reg'    => 'AB12 CDE',
            'from_date'      => '2025-08-22',
            'to_datetime'    => '2025-08-24T10:00:00',
        ])
            ->assertOk()
            ->json();

        // Fri (1500) + Sat (2000) = 3500
        $this->assertSame('active', $amended['status']);
        $this->assertSame(3500, $amended['total_minor']);
        $this->assertSame('GBP 35.00', $amended['total']);

        // GET reflects amended values (be flexible about offset)
        $fetched = $this->getJson("/api/v1/bookings/{$bookingId}")
            ->assertOk()
            ->json();

        $this->assertMatchesRegularExpression(
            '/^2025-08-24T10:00:00(?:Z|[+\-]\d{2}:\d{2})?$/',
            $fetched['to_datetime']
        );
        $this->assertSame(3500, $fetched['total_minor']);
    }

    #[Test]
    public function it_cancels_a_booking_and_then_allows_new_booking_for_same_car(): void
    {
        $created = $this->postJson('/api/v1/bookings', $this->validPayload())
            ->assertCreated()
            ->json();

        $bookingId = $created['id'];
        $reference = $created['reference'];

        $this->deleteJson("/api/v1/bookings/{$bookingId}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Booking cancelled', 'reference' => $reference]);

        $this->getJson("/api/v1/bookings/{$bookingId}")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);

        // same user+car allowed again because previous is cancelled
        $this->postJson('/api/v1/bookings', $this->validPayload())->assertCreated();
    }

    #[Test]
    public function it_refuses_to_amend_a_cancelled_booking(): void
    {
        $created = $this->postJson('/api/v1/bookings', $this->validPayload())
            ->assertCreated()
            ->json();

        $bookingId = $created['id'];

        $this->deleteJson("/api/v1/bookings/{$bookingId}")
            ->assertOk();

        // Include required fields so validation passes and we hit the controller's abort
        $this->putJson("/api/v1/bookings/{$bookingId}", [
            'customer_name'  => 'Alex Doe',
            'customer_email' => 'alex@example.com',
            'vehicle_reg'    => 'AB12 CDE',
            'from_date'      => '2025-08-22',
            'to_datetime'    => '2025-08-23T11:00:00',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot amend a cancelled booking.']);
    }

    #[Test]
    public function create_is_idempotent_for_same_payload(): void
    {
        $payload = [
            'customer_name'  => 'Alex Doe',
            'customer_email' => 'alex@example.com',
            'vehicle_reg'    => 'AB12 CDE',
            'from_date'      => '2025-08-22',
            'to_datetime'    => '2025-08-23T09:30:00',
        ];

        $this->postJson('/api/v1/bookings', $payload)
            ->assertCreated();

        // Simulate double-click with identical payload
        $this->postJson('/api/v1/bookings', $payload)
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'This booking has already been submitted.']);
    }

    #[Test]
    public function after_cancel_the_exact_same_payload_is_allowed_again(): void
    {
        $payload = [
            'customer_name'  => 'Alex Doe',
            'customer_email' => 'alex@example.com',
            'vehicle_reg'    => 'AB12 CDE',
            'from_date'      => '2025-08-22',
            'to_datetime'    => '2025-08-23T09:30:00',
        ];

        $first = $this->postJson('/api/v1/bookings', $payload)
            ->assertCreated()
            ->json();

        // Cancel
        $this->deleteJson("/api/v1/bookings/{$first['id']}")
            ->assertOk();

        // Now exact same payload should be accepted (fingerprint cleared on cancel)
        $this->postJson('/api/v1/bookings', $payload)
            ->assertCreated();
    }
}
