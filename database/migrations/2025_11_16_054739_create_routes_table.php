<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create enum types
        DB::statement("CREATE TYPE route_status AS ENUM ('draft', 'planned', 'in_progress', 'completed', 'cancelled')");
        DB::statement("CREATE TYPE optimization_method AS ENUM ('manual', 'nearest_neighbor', 'two_opt', 'google_maps', 'osrm')");

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_number', 50)->unique(); // RT-YYYY-NNNNNN
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('driver_id')->nullable()->constrained()->onDelete('set null');

            // Route information
            $table->string('name');
            $table->text('description')->nullable();

            // Planning dates
            $table->date('planned_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Distance metrics
            $table->decimal('planned_distance_km', 10, 2)->nullable(); // Total planned distance
            $table->decimal('actual_distance_km', 10, 2)->nullable(); // Actual distance traveled
            $table->decimal('distance_variance_km', 10, 2)->nullable(); // Difference
            $table->decimal('distance_variance_percent', 5, 2)->nullable(); // % variance

            // Duration metrics
            $table->integer('planned_duration_minutes')->nullable(); // Total planned time
            $table->integer('actual_duration_minutes')->nullable(); // Actual time
            $table->integer('duration_variance_minutes')->nullable(); // Difference
            $table->decimal('duration_variance_percent', 5, 2)->nullable(); // % variance

            // Cost tracking
            $table->decimal('estimated_fuel_cost', 10, 2)->nullable();
            $table->decimal('actual_fuel_cost', 10, 2)->nullable();
            $table->decimal('estimated_total_cost', 10, 2)->nullable(); // Including driver time, wear, etc.
            $table->decimal('actual_total_cost', 10, 2)->nullable();

            // Optimization
            $table->boolean('is_optimized')->default(false);
            $table->integer('stops_count')->default(0);
            $table->integer('completed_stops_count')->default(0);

            // Route metrics
            $table->decimal('fuel_consumption_liters', 8, 2)->nullable();
            $table->decimal('average_speed_kmh', 6, 2)->nullable();
            $table->integer('idle_time_minutes')->nullable();
            $table->integer('break_time_minutes')->nullable();

            // Additional data
            $table->json('optimization_params')->nullable(); // Parameters used for optimization
            $table->json('waypoints')->nullable(); // Encoded polyline or array of coordinates
            $table->text('notes')->nullable();
            $table->text('completion_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Add enum columns separately (PostgreSQL requirement)
        DB::statement("ALTER TABLE routes ADD COLUMN status route_status DEFAULT 'draft'");
        DB::statement("ALTER TABLE routes ADD COLUMN optimization_method optimization_method DEFAULT 'manual'");

        // Add indexes
        Schema::table('routes', function (Blueprint $table) {
            $table->index('organization_id');
            $table->index('vehicle_id');
            $table->index('driver_id');
            $table->index('planned_date');
            $table->index(['organization_id', 'planned_date']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
        DB::statement('DROP TYPE IF EXISTS route_status');
        DB::statement('DROP TYPE IF EXISTS optimization_method');
    }
};
