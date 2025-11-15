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
        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->string('type')->nullable(); // official_dealer, independent, specialized
            $table->text('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('FR');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Contact
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_name')->nullable();

            // Services
            $table->json('services_offered')->nullable(); // ['oil_change', 'tire_change', etc.]
            $table->json('brands_serviced')->nullable();

            // Rating
            $table->decimal('rating', 3, 2)->default(0); // 0-5
            $table->integer('total_interventions')->default(0);
            $table->decimal('average_cost', 10, 2)->nullable();
            $table->integer('average_delay_days')->nullable();

            // Status
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);

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
        Schema::dropIfExists('workshops');
    }
};
