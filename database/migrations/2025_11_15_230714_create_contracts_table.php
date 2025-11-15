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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->string('contract_number')->unique();
            $table->string('type'); // lease, insurance, maintenance, rental
            $table->string('supplier_name');
            $table->string('supplier_contact')->nullable();
            $table->string('supplier_email')->nullable();
            $table->string('supplier_phone')->nullable();

            // Contract details
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('duration_months')->nullable();
            $table->decimal('monthly_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->nullable();

            // Leasing specifics
            $table->integer('mileage_limit_annual')->nullable();
            $table->decimal('excess_mileage_cost', 8, 2)->nullable(); // Cost per km

            // Terms
            $table->text('terms')->nullable();
            $table->string('document_path')->nullable();

            // Status
            $table->string('status')->default('active'); // active, expired, terminated
            $table->boolean('auto_renewal')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
