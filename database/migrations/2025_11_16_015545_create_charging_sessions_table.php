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
        // Create enum type
        DB::statement("CREATE TYPE charging_session_status AS ENUM ('in_progress', 'completed', 'interrupted', 'failed')");

        Schema::create('charging_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('charging_station_id')->constrained()->onDelete('cascade');

            // Session information
            $table->string('session_number')->unique(); // CHG-YYYY-NNNNNN
            $table->enum('status', ['in_progress', 'completed', 'interrupted', 'failed'])->default('in_progress');

            // Timing
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->integer('duration_minutes')->nullable(); // Calculated

            // Battery levels
            $table->integer('battery_level_start_percent'); // 0-100
            $table->integer('battery_level_end_percent')->nullable(); // 0-100
            $table->integer('battery_charged_percent')->nullable(); // Difference

            // Energy
            $table->decimal('energy_delivered_kwh', 8, 2)->nullable();
            $table->decimal('average_power_kw', 8, 2)->nullable();
            $table->decimal('peak_power_kw', 8, 2)->nullable();

            // Costs
            $table->decimal('cost_per_kwh', 8, 4); // Rate at time of charging
            $table->decimal('connection_fee', 8, 2)->default(0);
            $table->decimal('total_cost', 8, 2)->nullable();

            // Interruption info
            $table->string('interruption_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('organization_id');
            $table->index('vehicle_id');
            $table->index('driver_id');
            $table->index('charging_station_id');
            $table->index('status');
            $table->index('start_time');
            $table->index(['organization_id', 'status']);
            $table->index(['vehicle_id', 'start_time']);
            $table->index(['charging_station_id', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charging_sessions');
        DB::statement("DROP TYPE IF EXISTS charging_session_status");
    }
};
