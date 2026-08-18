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
        Schema::create('reward_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // مثبت = دریافت
            // منفی = مصرف
            $table->integer('amount');

            // موجودی بعد از این تراکنش
            $table->integer('balance_after');

            $table->string('type');

            $table->string('description')->nullable();

            // سفارش، نظر، معرفی دوست و...
            $table->nullableMorphs('reference');

            $table->timestamps();

            $table->index([
                'user_id',
                'created_at',
            ]);

            $table->index([
                'user_id',
                'type',
            ]);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_point_transactions');
    }
};
