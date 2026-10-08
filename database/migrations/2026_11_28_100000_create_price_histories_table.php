<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاریخچه قیمت برای «نمودار قیمت» صفحه محصول
 * هر تنوع حداکثر یک ردیف در روز: قیمت پایه و قیمت نهایی (با تخفیف/کمپین).
 * با تغییر قیمت تنوع و یک snapshot روزانه (prices:snapshot) پر می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('final_price');
            $table->date('recorded_on');
            $table->timestamps();

            $table->unique(['product_variant_id', 'recorded_on']);
            $table->index(['product_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
