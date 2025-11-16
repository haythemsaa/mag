<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create enum types
        DB::statement("CREATE TYPE accounting_export_type AS ENUM ('costs', 'fuel', 'maintenance', 'contracts', 'all', 'custom')");
        DB::statement("CREATE TYPE accounting_export_format AS ENUM ('csv', 'excel', 'json', 'xml')");
        DB::statement("CREATE TYPE accounting_export_status AS ENUM ('pending', 'processing', 'completed', 'failed')");

        Schema::create('accounting_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // User who requested export
            $table->string('export_number')->unique(); // EXP-YYYY-NNNNNN

            // Export configuration
            $table->addColumn('accounting_export_type', 'type');
            $table->addColumn('accounting_export_format', 'format');
            $table->addColumn('accounting_export_status', 'status')->default('pending');

            // Date range
            $table->date('start_date');
            $table->date('end_date');

            // Account mapping configuration (JSON)
            // Example: {"fuel_account": "6061", "maintenance_account": "6155", ...}
            $table->json('account_mapping')->nullable();

            // Filters (JSON) - for custom exports
            // Example: {"vehicle_ids": [1,2,3], "cost_types": ["fuel", "maintenance"]}
            $table->json('filters')->nullable();

            // Export result
            $table->string('file_path')->nullable(); // Path to generated file
            $table->integer('records_exported')->nullable();
            $table->bigInteger('file_size_bytes')->nullable();

            // Processing metadata
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('processing_time_seconds')->nullable();

            // Error handling
            $table->text('error_message')->nullable();

            // Audit
            $table->string('downloaded_by')->nullable(); // User email who downloaded
            $table->timestamp('downloaded_at')->nullable();
            $table->integer('download_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'type']);
            $table->index(['start_date', 'end_date']);
            $table->index('export_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_exports');

        DB::statement('DROP TYPE IF EXISTS accounting_export_type');
        DB::statement('DROP TYPE IF EXISTS accounting_export_format');
        DB::statement('DROP TYPE IF EXISTS accounting_export_status');
    }
};
