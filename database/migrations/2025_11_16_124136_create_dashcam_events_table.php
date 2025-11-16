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
        DB::statement("CREATE TYPE dashcam_event_type AS ENUM (
            'harsh_braking',
            'harsh_acceleration',
            'harsh_cornering',
            'collision',
            'speeding',
            'distraction',
            'drowsiness',
            'phone_usage',
            'smoking',
            'no_seatbelt',
            'lane_departure',
            'forward_collision_warning',
            'tailgating',
            'rolling_stop',
            'other'
        )");

        DB::statement("CREATE TYPE dashcam_event_severity AS ENUM ('low', 'medium', 'high', 'critical')");
        DB::statement("CREATE TYPE dashcam_event_status AS ENUM ('pending_review', 'reviewed', 'acknowledged', 'disputed', 'dismissed', 'coaching_required')");

        Schema::create('dashcam_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_number')->unique(); // DCE-YYYY-NNNNNN

            // Event details
            $table->addColumn('dashcam_event_type', 'event_type');
            $table->addColumn('dashcam_event_severity', 'severity');
            $table->addColumn('dashcam_event_status', 'status')->default('pending_review');
            $table->timestamp('event_timestamp'); // When event occurred

            // Location (GPS correlation)
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address')->nullable(); // Reverse geocoded address
            $table->decimal('speed_kmh', 5, 2)->nullable(); // Speed at event time
            $table->decimal('speed_limit_kmh', 5, 2)->nullable(); // Posted speed limit

            // Event metrics
            $table->decimal('g_force_x', 5, 2)->nullable(); // Lateral G-force
            $table->decimal('g_force_y', 5, 2)->nullable(); // Longitudinal G-force
            $table->decimal('g_force_z', 5, 2)->nullable(); // Vertical G-force
            $table->decimal('max_g_force', 5, 2)->nullable(); // Maximum G-force recorded

            // Video metadata
            $table->string('dashcam_provider'); // mobileye, lytx, surfsight, smartwitness, etc.
            $table->string('external_event_id')->nullable(); // Provider's event ID
            $table->string('video_url')->nullable(); // Link to hosted video
            $table->string('video_thumbnail_url')->nullable();
            $table->integer('video_duration_seconds')->nullable();
            $table->timestamp('video_available_until')->nullable(); // Expiration date

            // Additional metadata (JSON)
            // Example: {"camera": "front", "audio": true, "weather": "rainy"}
            $table->json('metadata')->nullable();

            // Device information
            $table->string('device_id')->nullable(); // Dashcam device identifier
            $table->string('device_serial')->nullable();
            $table->string('firmware_version')->nullable();

            // Review and coaching
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->boolean('coaching_completed')->default(false);
            $table->timestamp('coaching_completed_at')->nullable();
            $table->foreignId('coached_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('coaching_notes')->nullable();

            // Dispute handling
            $table->boolean('is_disputed')->default(false);
            $table->text('dispute_reason')->nullable();
            $table->timestamp('disputed_at')->nullable();

            // Webhook tracking
            $table->json('webhook_payload')->nullable(); // Original webhook data
            $table->timestamp('received_at')->nullable(); // When webhook was received

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['organization_id', 'event_type']);
            $table->index(['organization_id', 'severity']);
            $table->index(['organization_id', 'status']);
            $table->index(['vehicle_id', 'event_timestamp']);
            $table->index(['driver_id', 'event_timestamp']);
            $table->index('event_number');
            $table->index('event_timestamp');
            $table->index('external_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashcam_events');

        DB::statement('DROP TYPE IF EXISTS dashcam_event_type');
        DB::statement('DROP TYPE IF EXISTS dashcam_event_severity');
        DB::statement('DROP TYPE IF EXISTS dashcam_event_status');
    }
};
