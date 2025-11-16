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
        DB::statement("CREATE TYPE theft_alert_type AS ENUM ('movement_outside_hours', 'geofence_violation', 'gps_signal_loss', 'unauthorized_ignition', 'towing_detected', 'speed_anomaly', 'route_deviation')");
        DB::statement("CREATE TYPE theft_alert_status AS ENUM ('pending', 'investigating', 'false_alarm', 'confirmed_theft', 'resolved')");
        DB::statement("CREATE TYPE theft_alert_severity AS ENUM ('low', 'medium', 'high', 'critical')");

        Schema::create('theft_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('geofence_id')->nullable()->constrained()->onDelete('set null'); // If related to geofence violation

            // Alert identification
            $table->string('alert_number')->unique(); // TH-YYYY-NNNNNN
            $table->string('type'); // Uses theft_alert_type enum
            $table->string('status')->default('pending'); // Uses theft_alert_status enum
            $table->string('severity'); // Uses theft_alert_severity enum

            // Alert details
            $table->text('description');
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();

            // Location data at time of alert
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address')->nullable();

            // Technical data
            $table->boolean('gps_signal_available')->default(true);
            $table->integer('signal_strength')->nullable(); // 0-100
            $table->decimal('speed_kmh', 8, 2)->nullable();
            $table->decimal('heading', 5, 2)->nullable(); // 0-360 degrees
            $table->boolean('engine_on')->nullable();
            $table->boolean('ignition_on')->nullable();

            // Alert triggers
            $table->boolean('outside_work_hours')->default(false);
            $table->boolean('geofence_exit_unauthorized')->default(false);
            $table->boolean('gps_jamming_suspected')->default(false);
            $table->boolean('towing_movement_detected')->default(false);
            $table->boolean('speed_anomaly_detected')->default(false);

            // Response tracking
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('investigation_started_at')->nullable();
            $table->text('investigation_notes')->nullable();
            $table->text('resolution_notes')->nullable();

            // Notification tracking
            $table->boolean('sms_sent')->default(false);
            $table->timestamp('sms_sent_at')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->boolean('push_notification_sent')->default(false);
            $table->timestamp('push_notification_sent_at')->nullable();

            // Police/Authorities
            $table->boolean('police_notified')->default(false);
            $table->timestamp('police_notified_at')->nullable();
            $table->string('police_reference_number')->nullable();
            $table->string('insurance_claim_number')->nullable();

            // Additional data
            $table->json('additional_data')->nullable(); // For storing extra context
            $table->text('admin_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('organization_id');
            $table->index('vehicle_id');
            $table->index('type');
            $table->index('status');
            $table->index('severity');
            $table->index('detected_at');
            $table->index(['organization_id', 'status']);
            $table->index(['vehicle_id', 'detected_at']);
            $table->index(['organization_id', 'severity', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theft_alerts');
        DB::statement('DROP TYPE IF EXISTS theft_alert_type');
        DB::statement('DROP TYPE IF EXISTS theft_alert_status');
        DB::statement('DROP TYPE IF EXISTS theft_alert_severity');
    }
};
