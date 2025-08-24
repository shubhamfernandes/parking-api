<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Capacity;
use PHPUnit\Framework\Attributes\Test;

class BookingCapacityTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_name'  => 'Alex One',
            'customer_email' => 'one@example.com',
            'vehicle_reg'    => 'AA11 AAA',
            'from_date'      => '2025-08-22',
            'to_datetime'    => '2025-08-23T09:00:00',
        ], $overrides);
    }

    #[Test]
    public function it_blocks_creation_when_day_is_full_and_allows_after_cancel(): void
    {
        // Make capacity = 1 for 2025-08-22
        Capacity::factory()->create([
            'day'      => '2025-08-22',
            'capacity' => 1,
        ]);

        // First booking occupies the day
        $first = $this->postJson('/api/v1/bookings', $this->payload())
            ->assertCreated()
            ->json();

        // Second user tries same day -> should be blocked (no space)
        $this->postJson('/api/v1/bookings', $this->payload([
            'customer_name'  => 'Bella Two',
            'customer_email' => 'two@example.com',
            'vehicle_reg'    => 'BB22 BBB',
        ]))
            ->assertStatus(409) // if your handler maps to 409; change if you use 422
            ->assertJsonFragment(['message' => 'No spaces available on 2025-08-22']);

        // Cancel the first → frees 1 slot
        $this->deleteJson("/api/v1/bookings/{$first['id']}")->assertOk();

        // Now a new booking succeeds
        $this->postJson('/api/v1/bookings', $this->payload([
            'customer_name'  => 'Bella Two',
            'customer_email' => 'two@example.com',
            'vehicle_reg'    => 'BB22 BBB',
        ]))
            ->assertCreated();
    }
}
