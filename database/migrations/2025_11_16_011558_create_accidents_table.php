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
        Schema::create('accidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();

            // Identification
            $table->string('accident_number')->unique();
            $table->datetime('accident_date');
            $table->string('location');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Gravité et responsabilité
            $table->enum('severity', ['minor', 'moderate', 'severe', 'total_loss']);
            $table->enum('responsibility', ['driver', 'third_party', 'shared', 'unknown'])->default('unknown');

            // Description
            $table->text('description');
            $table->boolean('police_report')->default(false);
            $table->string('police_report_number')->nullable();
            $table->boolean('injuries')->default(false);
            $table->integer('injured_count')->default(0);

            // Workflow
            $table->enum('status', [
                'declared',
                'in_progress',
                'expertised',
                'repaired',
                'closed'
            ])->default('declared');

            // Coûts
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('final_cost', 10, 2)->nullable();

            // Assurance
            $table->string('insurance_claim_number')->nullable();
            $table->date('insurance_claim_date')->nullable();
            $table->enum('insurance_status', [
                'pending',
                'accepted',
                'rejected',
                'partially_accepted'
            ])->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('accident_number');
            $table->index('severity');
            $table->index('status');
            $table->index('accident_date');
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'accident_date']);
            $table->index(['vehicle_id', 'accident_date']);
            $table->index(['driver_id', 'accident_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accidents');
    }
};
