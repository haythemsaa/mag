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
        Schema::create('costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');

            $table->string('category'); // fuel, maintenance, insurance, tax, parking, toll, fine, etc.
            $table->string('subcategory')->nullable();
            $table->date('date');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('EUR');

            // References
            $table->string('supplier_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('invoice_document')->nullable();
            $table->string('reference')->nullable();

            // Details
            $table->text('description')->nullable();
            $table->integer('mileage')->nullable();

            // Validation
            $table->boolean('validated')->default(false);
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validated_at')->nullable();

            // Accounting
            $table->string('account_code')->nullable();
            $table->boolean('vat_deductible')->default(true);
            $table->decimal('vat_amount', 10, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'vehicle_id', 'category', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costs');
    }
};
