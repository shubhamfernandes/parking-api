<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
            Schema::create('bookings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference')->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('vehicle_reg', 20);

            $table->date('from_date');                 // drop-off date
            $table->dateTime('to_datetime');           // pick-up datetime

            $table->unsignedBigInteger('total_minor')->default(0); // pence
            $table->char('currency', 3)->default(config('pricing.currency', env('PRICING_CURRENCY','GBP')));

            $table->string('status', 20)->index();     // active, cancelled
            $table->unsignedInteger('version')->default(1); //  locking


             //  Composite unique to block exact duplicate ACTIVE bookings:
            // same name + vehicle + from + to + status
            $table->unique(
                ['customer_name', 'vehicle_reg', 'from_date', 'to_datetime', 'status'],
                'uniq_active_booking_guard'
            );

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
