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
        Schema::create('inventories', function (Blueprint $table) {

            $table->id();

            // Warehouse title
            $table->string('title');

            // Unique slug
            $table->string('slug')->unique();

            // Warehouse address
            $table->text('address')->nullable();

            // Contact phone
            $table->string('phone')->nullable();

            // Sort order
            $table->unsignedInteger('sort')->default(0);

            // Active / Inactive
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->index(['status','sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
