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
        // Create enum types
        DB::statement("CREATE TYPE charging_station_type AS ENUM ('home', 'workplace', 'public', 'private', 'depot')");
        DB::statement("CREATE TYPE charging_connector_type AS ENUM ('Type 2', 'CCS', 'CHAdeMO', 'Tesla Supercharger', 'Type 1', 'AC')");
        DB::statement("CREATE TYPE charging_station_status AS ENUM ('available', 'occupied', 'maintenance', 'offline', 'reserved')");

        Schema::create('charging_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('site_id')->nullable()->constrained()->onDelete('set null');

            // Basic information
            $table->string('name');
            $table->string('station_code')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['home', 'workplace', 'public', 'private', 'depot'])->default('private');

            // Location
            $table->string('address');
            $table->string('city');
            $table->string('postal_code')->nullable();
            $table->string('country')->default('France');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Technical specifications
            $table->enum('connector_type', ['Type 2', 'CCS', 'CHAdeMO', 'Tesla Supercharger', 'Type 1', 'AC'])->default('Type 2');
            $table->decimal('max_power_kw', 8, 2); // Maximum charging power
            $table->boolean('supports_fast_charging')->default(false);
            $table->integer('number_of_ports')->default(1); // Number of charging ports

            // Status and availability
            $table->enum('status', ['available', 'occupied', 'maintenance', 'offline', 'reserved'])->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_maintenance_date')->nullable();
            $table->timestamp('next_maintenance_date')->nullable();

            // Costs
            $table->decimal('cost_per_kwh', 8, 4)->nullable(); // Cost per kWh
            $table->decimal('connection_fee', 8, 2)->nullable(); // Fixed connection fee
            $table->decimal('monthly_subscription', 8, 2')->nullable(); // Monthly subscription cost

            // Usage statistics
            $table->integer('total_sessions')->default(0);
            $table->decimal('total_energy_kwh', 12, 2)->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);

            // Network information (for public stations)
            $table->string('network_operator')->nullable(); // e.g., Ionity, ChargePoint
            $table->string('network_membership_required')->nullable();
            $table->string('access_card_required')->nullable();

            // Additional info
            $table->boolean('requires_reservation')->default(false);
            $table->integer('max_reservation_minutes')->nullable();
            $table->text('access_instructions')->nullable();
            $table->json('operating_hours')->nullable(); // Opening hours
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('organization_id');
            $table->index('site_id');
            $table->index('type');
            $table->index('status');
            $table->index('is_active');
            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'status']);
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charging_stations');
        DB::statement("DROP TYPE IF EXISTS charging_station_type");
        DB::statement("DROP TYPE IF EXISTS charging_connector_type");
        DB::statement("DROP TYPE IF EXISTS charging_station_status");
    }
};
