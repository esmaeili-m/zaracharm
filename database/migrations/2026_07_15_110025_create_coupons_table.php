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
        Schema::create('coupons', function (Blueprint $table) {

            $table->id();

            $table->foreignId('discount_id')
                ->constrained()
                ->cascadeOnDelete();

            // Coupon code
            $table->string('code')->unique();

            // Usage limit
            $table->unsignedInteger('usage_limit')->nullable();

            // Usage per user
            $table->unsignedInteger('usage_per_user')->default(1);

            // Used count
            $table->unsignedInteger('used_count')->default(0);

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
        Schema::dropIfExists('coupons');
    }
};
