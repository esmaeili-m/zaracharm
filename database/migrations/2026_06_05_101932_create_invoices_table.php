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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('invoice_number')->unique();

            $table->enum('status', [
                'pending',
                'paid',
                'failed',
                'refunded',
            ])->default('pending');

            // مبلغ محصولات
            $table->unsignedBigInteger('amount')->default(0);

            // مالیات
            $table->unsignedBigInteger('tax_amount')->default(0);

            // مبلغ نهایی
            $table->unsignedBigInteger('total_amount')->default(0);

            // مبلغ پرداخت شده
            $table->unsignedBigInteger('paid_amount')->default(0);

            $table->timestamp('paid_at')->nullable();

            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
