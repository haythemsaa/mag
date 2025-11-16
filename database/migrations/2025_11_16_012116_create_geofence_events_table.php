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
        // Create enum type for event types
        DB::statement("CREATE TYPE geofence_event_type AS ENUM ('entry', 'exit')");

        Schema::create('geofence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('geofence_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('gps_position_id')->nullable()->constrained()->onDelete('set null');

            // Event details
            $table->enum('event_type', ['entry', 'exit']);
            $table->timestamp('event_time');

            // Location at event
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('speed_kmh')->nullable();

            // Alert tracking
            $table->boolean('alert_sent')->default(false);
            $table->timestamp('alert_sent_at')->nullable();

            // Additional context
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('organization_id');
            $table->index('geofence_id');
            $table->index('vehicle_id');
            $table->index('driver_id');
            $table->index('event_type');
            $table->index('event_time');
            $table->index('alert_sent');
            $table->index(['organization_id', 'event_type']);
            $table->index(['organization_id', 'geofence_id']);
            $table->index(['organization_id', 'vehicle_id']);
            $table->index(['vehicle_id', 'event_time']);
            $table->index(['geofence_id', 'event_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofence_events');
        DB::statement("DROP TYPE IF EXISTS geofence_event_type");
    }
};
