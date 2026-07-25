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
        Schema::create('option_values', function (Blueprint $table) {

            $table->id();

            // Parent option
            $table->foreignId('option_id')
                ->constrained()
                ->cascadeOnDelete();

            // Display title (Black, White, 128GB, XL, ...)
            $table->string('title');

            // SEO friendly unique slug per option
            $table->string('slug');

            // Display order
            $table->unsignedInteger('sort')->default(0);

            // Active / Inactive
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->unique(['option_id', 'slug']);
            $table->index(['status', 'sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_values');
    }
};
