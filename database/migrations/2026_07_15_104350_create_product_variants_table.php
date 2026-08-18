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
        Schema::create('product_variants', function (Blueprint $table) {

            $table->id();

            // Parent product
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            // Stock Keeping Unit
            $table->string('sku')->nullable()->unique();

            // Barcode (EAN, UPC, ...)
            $table->string('barcode')->nullable();
            // Selling price
            $table->unsignedBigInteger('price')->nullable();

            // Original price before discount
            $table->unsignedBigInteger('compare_price')->nullable();

            // Purchase price
            $table->unsignedBigInteger('cost_price')->nullable();

            $table->boolean('is_default')->default(false);

            $table->unsignedInteger('weight')->nullable();

            $table->unsignedInteger('sort')->default(0);

            // Active / Inactive
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->index(['product_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
