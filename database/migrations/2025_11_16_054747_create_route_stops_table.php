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
        DB::statement("CREATE TYPE route_stop_type AS ENUM ('pickup', 'delivery', 'service', 'visit', 'break')");
        DB::statement("CREATE TYPE route_stop_status AS ENUM ('pending', 'arrived', 'in_progress', 'completed', 'skipped', 'failed')");

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->onDelete('cascade');
            $table->integer('stop_number'); // Order in the route (1, 2, 3, ...)
            $table->integer('optimized_stop_number')->nullable(); // Order after optimization

            // Location information
            $table->string('location_name');
            $table->text('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // Contact information
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();

            // Timing
            $table->timestamp('planned_arrival')->nullable();
            $table->timestamp('planned_departure')->nullable();
            $table->timestamp('actual_arrival')->nullable();
            $table->timestamp('actual_departure')->nullable();
            $table->integer('service_duration_minutes')->default(15); // Expected service time
            $table->integer('actual_service_duration_minutes')->nullable();

            // Time window (for delivery/pickup constraints)
            $table->time('time_window_start')->nullable(); // e.g., 9:00 AM
            $table->time('time_window_end')->nullable(); // e.g., 5:00 PM

            // Distance/duration from previous stop
            $table->decimal('distance_from_previous_km', 10, 2)->nullable();
            $table->integer('duration_from_previous_minutes')->nullable();

            // Tasks/Instructions
            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('requires_signature')->default(false);
            $table->boolean('requires_photo')->default(false);

            // Completion data
            $table->string('signature_path')->nullable();
            $table->json('photo_paths')->nullable(); // Array of photo file paths
            $table->text('completion_notes')->nullable();
            $table->text('failure_reason')->nullable(); // If status = failed/skipped

            // Package/delivery details (optional)
            $table->string('reference_number')->nullable(); // Customer order/package reference
            $table->integer('package_count')->nullable();
            $table->decimal('package_weight_kg', 8, 2)->nullable();
            $table->decimal('package_volume_m3', 8, 3)->nullable();

            // Priority
            $table->integer('priority')->default(1); // 1=low, 5=high

            $table->timestamps();
        });

        // Add enum columns separately (PostgreSQL requirement)
        DB::statement("ALTER TABLE route_stops ADD COLUMN type route_stop_type DEFAULT 'visit'");
        DB::statement("ALTER TABLE route_stops ADD COLUMN status route_stop_status DEFAULT 'pending'");

        // Add indexes
        Schema::table('route_stops', function (Blueprint $table) {
            $table->index('route_id');
            $table->index(['route_id', 'stop_number']);
            $table->index(['route_id', 'status']);
            $table->unique(['route_id', 'stop_number']); // Ensure unique stop numbers per route
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_stops');
        DB::statement('DROP TYPE IF EXISTS route_stop_type');
        DB::statement('DROP TYPE IF EXISTS route_stop_status');
    }
};
