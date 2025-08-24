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
        Schema::create('booking_days', function (Blueprint $table) {
            $table->id();
            $table->ulid('booking_id')->index();
            $table->date('day')->index(); // each occupied calendar day
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->unique(['booking_id', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_days');
    }
};
