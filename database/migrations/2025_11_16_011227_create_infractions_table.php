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
        Schema::create('infractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();

            // Identification
            $table->string('infraction_number')->unique();
            $table->string('reference_number')->nullable();

            // Détails infraction
            $table->enum('type', [
                'speeding',
                'red_light',
                'parking',
                'phone',
                'seatbelt',
                'alcohol',
                'dangerous_driving',
                'stop_sign',
                'wrong_way',
                'other'
            ]);

            // Date et lieu
            $table->date('infraction_date');
            $table->time('infraction_time')->nullable();
            $table->string('location');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Montants et paiement
            $table->decimal('amount', 10, 2);
            $table->decimal('reduced_amount', 10, 2)->nullable();
            $table->decimal('increased_amount', 10, 2)->nullable();
            $table->integer('points_deducted')->default(0);

            // Workflow
            $table->enum('status', [
                'received',
                'pending',
                'assigned',
                'contested',
                'paid',
                'cancelled'
            ])->default('received');

            // Dates importantes
            $table->date('due_date')->nullable();
            $table->date('reduced_due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->date('contested_date')->nullable();

            // Informations complémentaires
            $table->decimal('recorded_speed', 5, 2)->nullable();
            $table->decimal('speed_limit', 5, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index pour performance
            $table->index('infraction_number');
            $table->index('reference_number');
            $table->index('type');
            $table->index('status');
            $table->index('infraction_date');
            $table->index('due_date');
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'type']);
            $table->index(['vehicle_id', 'infraction_date']);
            $table->index(['driver_id', 'infraction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infractions');
    }
};
