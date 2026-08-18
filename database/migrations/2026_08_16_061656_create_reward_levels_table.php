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
        Schema::create('reward_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // حداقل امتیاز لازم برای ورود به این سطح
            $table->unsignedInteger('min_points')->default(0);

            // درصد تخفیف این سطح
            $table->unsignedTinyInteger('discount_percent')->default(0);

            // توضیحات سطح
            $table->text('description')->nullable();

            // رنگ نمایشی
            $table->string('color')->nullable();

            // آیکون
            $table->string('icon')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index([
                'is_active',
                'min_points',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_levels');
    }
};
