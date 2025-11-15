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
        Schema::create('gps_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained()->onDelete('set null');

            // GPS data
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('altitude', 8, 2)->nullable();
            $table->decimal('accuracy', 6, 2)->nullable();

            // Movement
            $table->decimal('speed', 6, 2)->default(0); // km/h
            $table->integer('heading')->nullable(); // 0-360 degrees
            $table->string('direction')->nullable(); // N, NE, E, etc.

            // Vehicle state
            $table->boolean('engine_on')->default(false);
            $table->string('engine_status')->nullable(); // on, off, idle
            $table->integer('fuel_level')->nullable(); // Percentage
            $table->integer('battery_level')->nullable(); // For electric vehicles
            $table->integer('odometer')->nullable();

            // Location
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // Timestamp
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['vehicle_id', 'recorded_at']);
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gps_positions');
    }
};
