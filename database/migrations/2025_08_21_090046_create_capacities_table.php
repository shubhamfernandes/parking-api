<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('capacities', function (Blueprint $table): void {
            // Calendar day acts as the primary key
            $table->date('day')->primary();

            // Override per-day capacity (defaults handled in code if not set)
            $table->unsignedInteger('capacity');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacities');
    }
};
