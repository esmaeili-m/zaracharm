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
        Schema::create('product_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_question_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->text('body');

            // پاسخ ثبت‌شده از پنل مدیریت (پاسخ کارشناس)
            $table->boolean('is_official')->default(false);

            // false = در انتظار تایید ، true = تایید شده و قابل نمایش
            $table->boolean('status')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_question_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_answers');
    }
};
