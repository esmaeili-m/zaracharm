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
        Schema::create('brands', function (Blueprint $table) {

            $table->id();

            // Brand name (Nike, Apple, Samsung, ...)
            $table->string('title');

            // SEO friendly url
            $table->string('slug')->unique();

            // Brand description
            $table->text('description')->nullable();

            // Official website
            $table->string('website')->nullable();

            // Country of origin
            $table->string('country')->nullable();

            // Display order
            $table->unsignedInteger('sort')->default(0);

            // Active / Inactive
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->index(['status', 'sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
