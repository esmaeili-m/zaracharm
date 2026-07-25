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
        Schema::create('product_variant_option_values', function (Blueprint $table) {
            $table->id();
            // Product Variant
            $table->foreignId('product_variant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Selected Option Value
            $table->foreignId('option_value_id')
                ->constrained()
                ->cascadeOnDelete();

            // Status
            $table->boolean('status')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate option values for a variant
            $table->unique(
                ['product_variant_id', 'option_value_id'],
                'pvov_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_values');
    }
};
