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
        Schema::create('inventory_items', function (Blueprint $table) {

            $table->id();

            // Warehouse
            $table->foreignId('inventory_id')
                ->constrained()
                ->cascadeOnDelete();

            // Product Variant
            $table->foreignId('product_variant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Current stock
            $table->unsignedInteger('quantity')->default(0);

            // Reserved stock for pending orders
            $table->unsignedInteger('reserved_quantity')->default(0);

            // Minimum stock alert
            $table->unsignedInteger('minimum_quantity')->default(0);

            // Active / Inactive
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->unique([
                'inventory_id',
                'product_variant_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
