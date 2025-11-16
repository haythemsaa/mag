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
        Schema::create('accident_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accident_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('type')->default('scene'); // scene, vehicle_damage, third_party, etc.
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('accident_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accident_photos');
    }
};
