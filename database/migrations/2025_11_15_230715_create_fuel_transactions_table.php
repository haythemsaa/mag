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
        Schema::create('fuel_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained()->onDelete('set null');

            $table->dateTime('transaction_date');
            $table->string('station_name')->nullable();
            $table->text('station_address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Fuel details
            $table->string('fuel_type'); // diesel, petrol, electric, etc.
            $table->decimal('quantity', 8, 2); // Litres or kWh
            $table->decimal('unit_price', 8, 3); // Price per liter
            $table->decimal('total_cost', 10, 2);
            $table->string('currency')->default('EUR');

            // Vehicle state
            $table->integer('mileage')->nullable();
            $table->integer('odometer_reading')->nullable();

            // Payment
            $table->string('payment_method')->nullable(); // card, cash, invoice
            $table->string('card_number')->nullable(); // Last 4 digits
            $table->string('invoice_number')->nullable();

            // Validation
            $table->boolean('validated')->default(false);
            $table->boolean('anomaly_detected')->default(false);
            $table->string('anomaly_reason')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'vehicle_id', 'transaction_date']);
            $table->index(['driver_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_transactions');
    }
};
