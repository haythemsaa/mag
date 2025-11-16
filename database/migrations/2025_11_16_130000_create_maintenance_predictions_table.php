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
        DB::statement("CREATE TYPE prediction_type AS ENUM (
            'maintenance_due',
            'part_failure',
            'cost_overrun',
            'fuel_efficiency_drop',
            'battery_degradation',
            'tire_replacement',
            'brake_wear',
            'oil_change',
            'inspection_due',
            'contract_expiry'
        )");

        DB::statement("CREATE TYPE prediction_confidence AS ENUM ('low', 'medium', 'high', 'very_high')");
        DB::statement("CREATE TYPE prediction_priority AS ENUM ('low', 'medium', 'high', 'critical')");
        DB::statement("CREATE TYPE prediction_status AS ENUM ('pending', 'acknowledged', 'scheduled', 'completed', 'dismissed', 'expired')");

        Schema::create('maintenance_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');

            // Prediction details
            $table->string('prediction_number')->unique(); // PRED-YYYY-NNNNNN
            $table->enum('prediction_type', [
                'maintenance_due',
                'part_failure',
                'cost_overrun',
                'fuel_efficiency_drop',
                'battery_degradation',
                'tire_replacement',
                'brake_wear',
                'oil_change',
                'inspection_due',
                'contract_expiry',
            ]);
            $table->string('title');
            $table->text('description');

            // Prediction metadata
            $table->enum('confidence', ['low', 'medium', 'high', 'very_high']);
            $table->enum('priority', ['low', 'medium', 'high', 'critical']);
            $table->enum('status', ['pending', 'acknowledged', 'scheduled', 'completed', 'dismissed', 'expired'])->default('pending');

            // Timeline
            $table->timestamp('predicted_date')->nullable(); // When issue is predicted to occur
            $table->integer('days_until_due')->nullable(); // Days until predicted date
            $table->timestamp('recommended_action_by')->nullable(); // Recommended action deadline
            $table->integer('current_odometer_km')->nullable(); // Odometer at prediction time
            $table->integer('predicted_odometer_km')->nullable(); // Predicted odometer at occurrence

            // Cost estimates
            $table->decimal('estimated_cost_min', 10, 2)->nullable();
            $table->decimal('estimated_cost_max', 10, 2)->nullable();
            $table->decimal('estimated_cost_avg', 10, 2)->nullable();

            // Algorithm details
            $table->string('algorithm_used')->nullable(); // e.g., 'time_based', 'mileage_based', 'ml_model', 'pattern_matching'
            $table->json('algorithm_params')->nullable(); // Parameters used
            $table->json('historical_data_summary')->nullable(); // Summary of data used for prediction

            // Recommendations
            $table->json('recommended_actions')->nullable(); // Array of recommended actions
            $table->json('preventive_measures')->nullable(); // Suggested preventive measures

            // Related records
            $table->foreignId('related_maintenance_id')->nullable()->constrained('maintenances')->onDelete('set null');
            $table->foreignId('related_contract_id')->nullable()->constrained('contracts')->onDelete('set null');

            // Acknowledgment and handling
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgment_notes')->nullable();

            // Scheduling
            $table->foreignId('scheduled_maintenance_id')->nullable()->constrained('maintenances')->onDelete('set null');
            $table->timestamp('scheduled_at')->nullable();

            // Completion/Dismissal
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('dismissal_reason')->nullable();

            // Accuracy tracking (for ML improvement)
            $table->boolean('was_accurate')->nullable(); // Was prediction accurate?
            $table->integer('accuracy_variance_days')->nullable(); // Difference between predicted and actual
            $table->text('accuracy_notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['organization_id', 'status']);
            $table->index(['vehicle_id', 'status']);
            $table->index(['prediction_type', 'status']);
            $table->index('predicted_date');
            $table->index('priority');
            $table->index('confidence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_predictions');
        DB::statement('DROP TYPE IF EXISTS prediction_type');
        DB::statement('DROP TYPE IF EXISTS prediction_confidence');
        DB::statement('DROP TYPE IF EXISTS prediction_priority');
        DB::statement('DROP TYPE IF EXISTS prediction_status');
    }
};
