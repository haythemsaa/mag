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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('site_id')->nullable()->constrained()->onDelete('set null');
            $table->string('vin')->unique(); // Vehicle Identification Number
            $table->string('registration')->unique(); // Immatriculation
            $table->string('brand'); // Marque
            $table->string('model'); // Modèle
            $table->string('version')->nullable();
            $table->string('type'); // VL, VU, PL, etc.
            $table->string('category')->nullable(); // SUV, Berline, etc.
            $table->string('color')->nullable();
            $table->integer('year')->nullable();
            $table->integer('seats')->default(5);

            // Acquisition
            $table->string('acquisition_mode'); // purchase, lease, rental
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_value', 12, 2)->nullable();
            $table->decimal('current_value', 12, 2)->nullable();

            // Technical
            $table->string('fuel_type'); // diesel, petrol, electric, hybrid, etc.
            $table->integer('fuel_capacity')->nullable(); // Litres
            $table->string('engine_type')->nullable();
            $table->integer('engine_power')->nullable(); // CV
            $table->integer('co2_emissions')->nullable(); // g/km
            $table->decimal('consumption_theory', 5, 2)->nullable(); // L/100km

            // Mileage
            $table->integer('current_mileage')->default(0);
            $table->integer('initial_mileage')->default(0);
            $table->date('last_mileage_update')->nullable();

            // Status
            $table->string('status')->default('available'); // available, assigned, in_maintenance, accident, sold, etc.
            $table->foreignId('current_driver_id')->nullable()->constrained('drivers')->onDelete('set null');

            // Documents
            $table->string('registration_document')->nullable(); // Carte grise
            $table->date('technical_control_date')->nullable(); // Contrôle technique
            $table->date('next_technical_control')->nullable();
            $table->string('insurance_policy')->nullable();
            $table->date('insurance_expiry')->nullable();

            // GPS/Telemetry
            $table->string('gps_device_id')->nullable();
            $table->boolean('gps_enabled')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['site_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
