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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('site_id')->nullable()->constrained()->onDelete('set null');
            $table->string('employee_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('phone_mobile')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();

            // License information
            $table->string('license_number')->unique();
            $table->string('license_type'); // B, C, D, etc.
            $table->date('license_issue_date')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->integer('license_points')->default(12);

            // Medical
            $table->date('medical_check_date')->nullable();
            $table->date('next_medical_check')->nullable();

            // Professional
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('contract_type')->nullable(); // CDI, CDD, Interim

            // Scoring
            $table->integer('eco_driving_score')->default(0); // 0-100
            $table->integer('total_infractions')->default(0);
            $table->decimal('total_distance', 12, 2)->default(0);

            // Status
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('active'); // active, suspended, terminated

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
