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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // نوع تراکنش
            $table->tinyInteger('type');
            // 1 charge
            // 2 purchase
            // 3 withdraw
            // 4 refund
            // 5 commission

            // وضعیت
            $table->tinyInteger('status')->default(0);
            // 0 pending
            // 1 success
            // 2 failed

            // مبالغ
            $table->bigInteger('amount');        // مبلغ اصلی
            $table->bigInteger('tax_amount')->default(0); // مالیات
            $table->bigInteger('total_amount');  // مبلغ نهایی (amount + tax)

            // موجودی قبل و بعد
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');

            // اتصال به هر مدل (دوره، سفارش و ...)
            $table->nullableMorphs('transactionable');

            // اطلاعات درگاه یا اضافی
            $table->json('meta')->nullable();

            $table->string('authority')->nullable(); // زرین‌پال
            $table->string('ref_id')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
