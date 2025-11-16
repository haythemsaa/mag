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
        DB::statement("CREATE TYPE geofence_shape_type AS ENUM ('circle', 'polygon')");
        DB::statement("CREATE TYPE geofence_type AS ENUM ('authorized', 'forbidden', 'client_site', 'depot', 'parking', 'service_area', 'delivery_zone', 'restricted')");

        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();

            // Geofence type and shape
            $table->enum('type', ['authorized', 'forbidden', 'client_site', 'depot', 'parking', 'service_area', 'delivery_zone', 'restricted'])->default('authorized');
            $table->enum('shape', ['circle', 'polygon'])->default('circle');

            // Circle properties
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->integer('radius_meters')->nullable(); // for circle geofences

            // Polygon properties (stored as JSON array of lat/lng coordinates)
            $table->json('polygon_coordinates')->nullable(); // for polygon geofences

            // Alert settings
            $table->boolean('alert_on_entry')->default(false);
            $table->boolean('alert_on_exit')->default(false);
            $table->boolean('is_active')->default(true);

            // Time restrictions (optional)
            $table->time('active_from_time')->nullable();
            $table->time('active_to_time')->nullable();
            $table->json('active_days')->nullable(); // Array of day numbers [1-7] for Mon-Sun

            // Additional metadata
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('color', 7)->default('#3B82F6'); // Hex color for map display

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('organization_id');
            $table->index('type');
            $table->index('is_active');
            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'is_active']);
            $table->index(['center_latitude', 'center_longitude']); // For spatial queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofences');
        DB::statement("DROP TYPE IF EXISTS geofence_shape_type");
        DB::statement("DROP TYPE IF EXISTS geofence_type");
    }
};
