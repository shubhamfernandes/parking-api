<?php

namespace Tests\Feature\Api;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze "today" so rules like after_or_equal:today behave deterministically
        Carbon::setTestNow('2025-08-21 09:00:00');
    }

    private function base(): array
    {
        return [
            'customer_name'  => 'Casey',
            'customer_email' => 'casey@example.com',
            'vehicle_reg'    => 'CC33 CCC',
        ];
    }

    #[Test]
    public function store_rejects_past_from_date(): void
    {
        $this->postJson('/api/v1/bookings', array_merge($this->base(), [
            'from_date'   => '2025-08-20',                // past relative to 2025-08-21
            'to_datetime' => '2025-08-22T09:00:00',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_date']);
    }

    #[Test]
    public function store_rejects_to_before_from(): void
    {
        $this->postJson('/api/v1/bookings', array_merge($this->base(), [
            'from_date'   => '2025-08-22',
            'to_datetime' => '2025-08-21T09:00:00', // before from_date
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_datetime']);
    }

    #[Test]
    public function update_rejects_to_before_from(): void
    {
        // Create a valid booking first
        $created = $this->postJson('/api/v1/bookings', array_merge($this->base(), [
            'from_date'   => '2025-08-22',
            'to_datetime' => '2025-08-23T09:00:00',
        ]))
            ->assertCreated()
            ->json();

        // Attempt to amend with invalid date range (include required fields)
        $this->putJson("/api/v1/bookings/{$created['id']}", array_merge($this->base(), [
            'from_date'   => '2025-08-22',
            'to_datetime' => '2025-08-21T09:00:00',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_datetime']);
    }
}
