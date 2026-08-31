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

            // سفارش مربوط به فاکتور
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            // کاربر
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // شماره فاکتور
            $table->string('invoice_number')
                ->unique();

            // وضعیت فاکتور
            $table->enum('status', [
                'draft',
                'unpaid',
                'paid',
                'cancelled',
                'refunded',
            ])->default('unpaid');

            // مبالغ
            $table->unsignedBigInteger('subtotal');

            $table->unsignedBigInteger('discount_amount')
                ->default(0);

            $table->unsignedBigInteger('tax_amount')
                ->default(0);

            $table->unsignedBigInteger('shipping_amount')
                ->default(0);

            $table->unsignedBigInteger('total_amount');

            // زمان پرداخت
            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamps();

            $table->softDeletes();
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
