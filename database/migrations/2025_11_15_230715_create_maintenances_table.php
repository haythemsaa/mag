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
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('workshop_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('driver_id')->nullable()->constrained()->onDelete('set null');

            $table->string('type'); // preventive, curative, recall
            $table->string('category')->nullable(); // oil_change, tire_change, brake, etc.
            $table->string('reference_number')->unique();

            // Scheduling
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Mileage
            $table->integer('mileage_at_service')->nullable();
            $table->integer('next_service_mileage')->nullable();
            $table->date('next_service_date')->nullable();

            // Cost
            $table->decimal('labor_cost', 10, 2)->default(0);
            $table->decimal('parts_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);

            // Details
            $table->text('description')->nullable();
            $table->text('work_done')->nullable();
            $table->text('parts_replaced')->nullable();
            $table->text('recommendations')->nullable();

            // Documents
            $table->string('invoice_number')->nullable();
            $table->string('invoice_document')->nullable();

            // Status
            $table->string('status')->default('scheduled'); // scheduled, in_progress, completed, cancelled
            $table->integer('downtime_hours')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'vehicle_id', 'status']);
            $table->index(['scheduled_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
