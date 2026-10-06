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
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();

            $table->string('return_number')->unique();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // pending | approved | rejected | received | refunded | cancelled
            $table->string('status')->default('pending');

            $table->string('reason');
            $table->text('description')->nullable();
            $table->text('admin_note')->nullable();

            // مبلغ قابل استرداد (پیش‌فرض: جمع اقلام؛ مدیر می‌تواند قبل از استرداد اصلاح کند)
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('return_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity');

            // مبلغ همین قلم در زمان ثبت درخواست (قیمت واحد × تعداد)
            $table->unsignedBigInteger('amount');

            $table->timestamps();

            $table->unique(['return_request_id', 'order_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');
    }
};
