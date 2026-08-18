<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_rewards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained()
                ->cascadeOnDelete();

            // 0 => Percent Discount
            // 1 => Fixed Discount
            // 2 => Free Shipping
            // 3 => Gift Product
            // 4 => Cashback
            $table->tinyInteger('reward_type');

            // مقدار تخفیف یا مبلغ کش بک
            $table->decimal('value', 12, 2)
                ->nullable();

            // سقف تخفیف برای درصدی
            $table->decimal('max_value', 12, 2)
                ->nullable();

            // محصول هدیه
            $table->foreignId('product_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->timestamps();

            $table->index('reward_type');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('campaign_rewards');
    }
};
