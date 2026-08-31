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
        Schema::create('invoice_items', function (Blueprint $table) {

            $table->id();

            // فاکتور
            $table->foreignId('invoice_id')
                ->constrained()
                ->cascadeOnDelete();

            // محصول / واریانت
            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            // Snapshot محصول
            $table->string('product_name');

            // Snapshot واریانت
            $table->string('variant_name')
                ->nullable();

            // تعداد
            $table->unsignedInteger('quantity');

            // قیمت واحد در زمان خرید
            $table->unsignedBigInteger('unit_price');

            // مبلغ کل این آیتم
            $table->unsignedBigInteger('total_price');

            // ویژگی‌های انتخاب‌شده
            $table->json('attributes')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');

    }
};
