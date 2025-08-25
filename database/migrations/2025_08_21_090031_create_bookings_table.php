<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('reference')->unique(); // BK-<ULID>
            $table->string('customer_name');
            $table->string('customer_email')->index();

            $table->string('vehicle_reg'); // raw reg
            // Stored generated column (UPPER + strip spaces) used for fast lookups
            $table->string('vehicle_reg_normalized')->storedAs("UPPER(REPLACE(vehicle_reg, ' ', ''))");
            $table->index('vehicle_reg_normalized', 'bookings_reg_norm_idx');

            // Note: tests may pass 'YYYY-MM-DD' or 'YYYY-MM-DDTHH:MM:SS'
            $table->dateTime('from_date');   // start-of-day is enforced by code
            $table->dateTime('to_datetime'); // checkout moment (exclusive)

            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('currency', 3)->default('GBP');

            // Idempotency (nullable so you can rebook after cancel)
            $table->string('request_fingerprint', 64)
                ->nullable()
                ->unique('uniq_bookings_request_fingerprint');

            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // Overlap & duplicate guards
            $table->index(['from_date', 'to_datetime', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
