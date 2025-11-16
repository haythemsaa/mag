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
        // Vehicles table indexes
        Schema::table('vehicles', function (Blueprint $table) {
            $table->index('status');
            $table->index('fuel_type');
            $table->index('category');
            $table->index('ownership_type');
            $table->index('registration_number');
            $table->index('vin');
            $table->index('current_mileage');
            $table->index('purchase_date');
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'category']);
            $table->index(['organization_id', 'fuel_type']);
        });

        // Drivers table indexes
        Schema::table('drivers', function (Blueprint $table) {
            $table->index('status');
            $table->index('employment_type');
            $table->index('license_number');
            $table->index('license_expiry_date');
            $table->index('email');
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'employment_type']);
        });

        // Maintenances table indexes
        Schema::table('maintenances', function (Blueprint $table) {
            $table->index('status');
            $table->index('type');
            $table->index('scheduled_date');
            $table->index('completed_date');
            $table->index(['vehicle_id', 'status']);
            $table->index(['vehicle_id', 'scheduled_date']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'scheduled_date']);
        });

        // Fuel Transactions table indexes
        Schema::table('fuel_transactions', function (Blueprint $table) {
            $table->index('fuel_type');
            $table->index('date');
            $table->index('is_anomaly');
            $table->index(['vehicle_id', 'date']);
            $table->index(['vehicle_id', 'fuel_type']);
            $table->index(['organization_id', 'date']);
            $table->index(['organization_id', 'fuel_type']);
            $table->index(['organization_id', 'is_anomaly']);
        });

        // Costs table indexes
        Schema::table('costs', function (Blueprint $table) {
            $table->index('category');
            $table->index('date');
            $table->index('is_validated');
            $table->index('payment_method');
            $table->index(['vehicle_id', 'category']);
            $table->index(['vehicle_id', 'date']);
            $table->index(['organization_id', 'category']);
            $table->index(['organization_id', 'date']);
            $table->index(['organization_id', 'is_validated']);
        });

        // Contracts table indexes
        Schema::table('contracts', function (Blueprint $table) {
            $table->index('type');
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('contract_number');
            $table->index(['vehicle_id', 'type']);
            $table->index(['vehicle_id', 'status']);
            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'end_date']);
        });

        // GPS Positions table indexes
        Schema::table('gps_positions', function (Blueprint $table) {
            $table->index('timestamp');
            $table->index(['vehicle_id', 'timestamp']);
            $table->index(['organization_id', 'timestamp']);
            $table->index(['latitude', 'longitude']);
        });

        // Sites table indexes
        Schema::table('sites', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('name');
            $table->index(['organization_id', 'is_active']);
        });

        // Workshops table indexes
        Schema::table('workshops', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('rating');
            $table->index('name');
            $table->index(['organization_id', 'is_active']);
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('is_active');
            $table->index(['organization_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Vehicles table indexes
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['fuel_type']);
            $table->dropIndex(['category']);
            $table->dropIndex(['ownership_type']);
            $table->dropIndex(['registration_number']);
            $table->dropIndex(['vin']);
            $table->dropIndex(['current_mileage']);
            $table->dropIndex(['purchase_date']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['organization_id', 'category']);
            $table->dropIndex(['organization_id', 'fuel_type']);
        });

        // Drivers table indexes
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['employment_type']);
            $table->dropIndex(['license_number']);
            $table->dropIndex(['license_expiry_date']);
            $table->dropIndex(['email']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['organization_id', 'employment_type']);
        });

        // Maintenances table indexes
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['type']);
            $table->dropIndex(['scheduled_date']);
            $table->dropIndex(['completed_date']);
            $table->dropIndex(['vehicle_id', 'status']);
            $table->dropIndex(['vehicle_id', 'scheduled_date']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['organization_id', 'scheduled_date']);
        });

        // Fuel Transactions table indexes
        Schema::table('fuel_transactions', function (Blueprint $table) {
            $table->dropIndex(['fuel_type']);
            $table->dropIndex(['date']);
            $table->dropIndex(['is_anomaly']);
            $table->dropIndex(['vehicle_id', 'date']);
            $table->dropIndex(['vehicle_id', 'fuel_type']);
            $table->dropIndex(['organization_id', 'date']);
            $table->dropIndex(['organization_id', 'fuel_type']);
            $table->dropIndex(['organization_id', 'is_anomaly']);
        });

        // Costs table indexes
        Schema::table('costs', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['date']);
            $table->dropIndex(['is_validated']);
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['vehicle_id', 'category']);
            $table->dropIndex(['vehicle_id', 'date']);
            $table->dropIndex(['organization_id', 'category']);
            $table->dropIndex(['organization_id', 'date']);
            $table->dropIndex(['organization_id', 'is_validated']);
        });

        // Contracts table indexes
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);
            $table->dropIndex(['start_date']);
            $table->dropIndex(['end_date']);
            $table->dropIndex(['contract_number']);
            $table->dropIndex(['vehicle_id', 'type']);
            $table->dropIndex(['vehicle_id', 'status']);
            $table->dropIndex(['organization_id', 'type']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['organization_id', 'end_date']);
        });

        // GPS Positions table indexes
        Schema::table('gps_positions', function (Blueprint $table) {
            $table->dropIndex(['timestamp']);
            $table->dropIndex(['vehicle_id', 'timestamp']);
            $table->dropIndex(['organization_id', 'timestamp']);
            $table->dropIndex(['latitude', 'longitude']);
        });

        // Sites table indexes
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['name']);
            $table->dropIndex(['organization_id', 'is_active']);
        });

        // Workshops table indexes
        Schema::table('workshops', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['rating']);
            $table->dropIndex(['name']);
            $table->dropIndex(['organization_id', 'is_active']);
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['organization_id', 'is_active']);
        });
    }
};
