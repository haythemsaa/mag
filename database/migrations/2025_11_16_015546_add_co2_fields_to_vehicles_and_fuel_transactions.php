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
        // Add CO2 emission fields to vehicles table
        Schema::table('vehicles', function (Blueprint $table) {
            // CO2 Emissions (g/km)
            $table->decimal('co2_emissions_gkm', 8, 2)->nullable()->after('consumption');
            $table->decimal('co2_emissions_wltp_gkm', 8, 2)->nullable()->after('co2_emissions_gkm');

            // Total emissions tracking
            $table->decimal('total_co2_kg', 12, 2)->default(0)->after('co2_emissions_wltp_gkm');

            // Environmental class
            $table->string('euro_standard')->nullable()->after('total_co2_kg'); // Euro 5, Euro 6, etc.
            $table->string('crit_air_label')->nullable()->after('euro_standard'); // Crit'Air 1-5

            $table->index('co2_emissions_gkm');
            $table->index('euro_standard');
        });

        // Add CO2 fields to fuel_transactions table
        Schema::table('fuel_transactions', function (Blueprint $table) {
            // CO2 emissions for this transaction
            $table->decimal('co2_emissions_kg', 8, 2)->nullable()->after('total_cost');

            // Emission factor used (kg CO2 per liter)
            $table->decimal('emission_factor_kg_per_liter', 8, 4)->nullable()->after('co2_emissions_kg');

            $table->index('co2_emissions_kg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['vehicles_co2_emissions_gkm_index']);
            $table->dropIndex(['vehicles_euro_standard_index']);

            $table->dropColumn([
                'co2_emissions_gkm',
                'co2_emissions_wltp_gkm',
                'total_co2_kg',
                'euro_standard',
                'crit_air_label',
            ]);
        });

        Schema::table('fuel_transactions', function (Blueprint $table) {
            $table->dropIndex(['fuel_transactions_co2_emissions_kg_index']);

            $table->dropColumn([
                'co2_emissions_kg',
                'emission_factor_kg_per_liter',
            ]);
        });
    }
};
