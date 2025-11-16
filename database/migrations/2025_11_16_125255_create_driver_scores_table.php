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
        Schema::create('driver_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->constrained()->onDelete('cascade');

            // Period (monthly scoring)
            $table->integer('year');
            $table->integer('month'); // 1-12

            // Overall score (0-100)
            $table->decimal('total_score', 5, 2); // e.g., 87.50

            // Component scores (0-100 each)
            $table->decimal('safety_score', 5, 2); // Based on accidents, infractions, dashcam events
            $table->decimal('efficiency_score', 5, 2); // Based on fuel consumption, idle time
            $table->decimal('compliance_score', 5, 2); // Based on infractions, violations
            $table->decimal('behavior_score', 5, 2); // Based on harsh events (braking, acceleration, cornering)

            // Metrics used for calculation
            $table->integer('total_trips')->default(0);
            $table->decimal('total_distance_km', 10, 2)->default(0);
            $table->integer('total_drive_time_hours')->default(0);

            // Safety metrics
            $table->integer('accidents_count')->default(0);
            $table->integer('infractions_count')->default(0);
            $table->integer('dashcam_events_count')->default(0);
            $table->integer('critical_events_count')->default(0); // Critical dashcam events

            // Efficiency metrics
            $table->decimal('avg_fuel_consumption', 5, 2)->nullable(); // L/100km
            $table->decimal('idle_time_percent', 5, 2)->nullable();
            $table->decimal('avg_speed_kmh', 5, 2)->nullable();

            // Behavior metrics
            $table->integer('harsh_braking_count')->default(0);
            $table->integer('harsh_acceleration_count')->default(0);
            $table->integer('harsh_cornering_count')->default(0);
            $table->integer('speeding_events_count')->default(0);

            // Trend analysis
            $table->decimal('score_change', 6, 2)->nullable(); // Change from previous period (+/- X.XX)
            $table->string('trend')->nullable(); // 'improving', 'declining', 'stable'

            // Ranking
            $table->integer('organization_rank')->nullable(); // Rank within organization
            $table->integer('total_drivers_in_org')->nullable();

            // Calculation metadata
            $table->timestamp('calculated_at')->nullable();
            $table->json('calculation_details')->nullable(); // Detailed breakdown

            $table->timestamps();

            // Indexes
            $table->unique(['driver_id', 'year', 'month']); // One score per driver per month
            $table->index(['organization_id', 'year', 'month']);
            $table->index(['organization_id', 'total_score']);
            $table->index('calculated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_scores');
    }
};
