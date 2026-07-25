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
        Schema::create('options', function (Blueprint $table) {

            $table->id();

            // Option name (Color, Size, Memory, ...)
            $table->string('title');

            // SEO friendly unique slug
            $table->string('slug')->unique();

            // Display order in admin and frontend
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
        Schema::dropIfExists('options');
    }
};
