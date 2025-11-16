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
        Schema::table('vehicles', function (Blueprint $table) {
            // Electric vehicle specific fields
            $table->boolean('is_electric')->default(false)->after('fuel_type');
            $table->boolean('is_hybrid')->default(false)->after('is_electric');

            // Battery information (for electric/hybrid vehicles)
            $table->decimal('battery_capacity_kwh', 8, 2)->nullable()->after('is_hybrid');
            $table->integer('battery_current_level_percent')->nullable()->after('battery_capacity_kwh'); // 0-100
            $table->decimal('battery_current_kwh', 8, 2)->nullable()->after('battery_current_level_percent');
            $table->integer('estimated_range_km')->nullable()->after('battery_current_kwh');
            $table->integer('max_range_km')->nullable()->after('estimated_range_km');

            // Charging capabilities
            $table->string('charging_port_type')->nullable()->after('max_range_km'); // Type 2, CCS, CHAdeMO, Tesla
            $table->decimal('max_charging_power_kw', 8, 2)->nullable()->after('charging_port_type');
            $table->boolean('supports_fast_charging')->default(false)->after('max_charging_power_kw');

            // Efficiency
            $table->decimal('kwh_per_100km', 8, 2)->nullable()->after('supports_fast_charging'); // Consumption
            $table->decimal('wltp_range_km', 8, 2)->nullable()->after('kwh_per_100km'); // Official WLTP range

            // Battery health
            $table->integer('battery_health_percent')->nullable()->after('wltp_range_km'); // 0-100
            $table->date('battery_warranty_expiry')->nullable()->after('battery_health_percent');
            $table->timestamp('last_full_charge_at')->nullable()->after('battery_warranty_expiry');

            // Indexes
            $table->index('is_electric');
            $table->index('is_hybrid');
            $table->index(['is_electric', 'is_hybrid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['vehicles_is_electric_index']);
            $table->dropIndex(['vehicles_is_hybrid_index']);
            $table->dropIndex(['vehicles_is_electric_is_hybrid_index']);

            $table->dropColumn([
                'is_electric',
                'is_hybrid',
                'battery_capacity_kwh',
                'battery_current_level_percent',
                'battery_current_kwh',
                'estimated_range_km',
                'max_range_km',
                'charging_port_type',
                'max_charging_power_kw',
                'supports_fast_charging',
                'kwh_per_100km',
                'wltp_range_km',
                'battery_health_percent',
                'battery_warranty_expiry',
                'last_full_charge_at',
            ]);
        });
    }
};
