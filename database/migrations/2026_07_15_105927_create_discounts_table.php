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
        Schema::create('discounts', function (Blueprint $table) {

            $table->id();

            // Campaign title
            $table->string('title');

            // Fixed / Percent
            $table->unsignedTinyInteger('type');

            // Discount amount
            $table->unsignedBigInteger('value');

            // Maximum discount amount
            $table->unsignedBigInteger('maximum_discount')->nullable();

            // Minimum purchase amount
            $table->unsignedBigInteger('minimum_purchase')->nullable();

            // Start date
            $table->timestamp('starts_at')->nullable();

            // End date
            $table->timestamp('ends_at')->nullable();

            // Active / Inactive
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
