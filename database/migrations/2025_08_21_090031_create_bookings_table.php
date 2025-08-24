<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('reference')->unique();

            $table->string('customer_name');
            $table->string('customer_email')->index('idx_bookings_email');

            $table->string('vehicle_reg', 20);

            // Normalized reg (UPPER, no spaces) for fast lookups
            $table->string('vehicle_reg_normalized', 20)
                    ->storedAs("UPPER(REPLACE(vehicle_reg, ' ', ''))"); // generated column
            $table->index('vehicle_reg_normalized', 'idx_bookings_reg_norm');

            // Dates
            $table->date('from_date');        // drop-off date (00:00)
            $table->dateTime('to_datetime');  // pick-up date+time

            // Money
            $table->unsignedBigInteger('total_minor')->default(0); // pence
            $table->char('currency', 3)
                ->default(config('pricing.currency', env('PRICING_CURRENCY', 'GBP')));

            // Status / versioning
            $table->string('status', 20)->index(); // active, cancelled
            $table->unsignedInteger('version')->default(1);

            // Idempotency
            $table->string('request_fingerprint', 64)->nullable()->unique();

            // Guard exact duplicate ACTIVE bookings by same name+from+to+status
            $table->unique(
                ['customer_name', 'vehicle_reg', 'from_date', 'to_datetime', 'status'],
                'uniq_active_booking_guard'
            );

            // Composite index to power duplicate-active check quickly
            $table->index(
                ['customer_email', 'vehicle_reg_normalized', 'status'],
                'idx_bookings_email_reg_status'
            );

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
