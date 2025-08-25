<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_days', function (Blueprint $table): void {
            $table->id();

            $table->ulid('booking_id')->index();
            $table->date('day'); // occupied calendar day

            // Uniqueness per booking/day
            $table->unique(['booking_id', 'day']);

            // Composite index helps counts/locks on day and joins back to bookings
            $table->index(['day', 'booking_id'], 'idx_booking_days_day_booking');

            // FK
            $table->foreign('booking_id')
                ->references('id')
                ->on('bookings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_days');
    }
};
