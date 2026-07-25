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
        Schema::create('product_options', function (Blueprint $table) {

            $table->id();

            // Product
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            // Option
            $table->foreignId('option_id')
                ->constrained()
                ->cascadeOnDelete();

            // Display order
            $table->unsignedInteger('sort')->default(0);

            // Is selecting this option required?
            $table->boolean('is_required')->default(true);

            // Active / Inactive
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->unique(['product_id', 'option_id']);

            $table->index(['status', 'sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
