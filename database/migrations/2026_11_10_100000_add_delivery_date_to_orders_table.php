<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاریخ ارسالِ انتخاب‌شده توسط مشتری در Checkout
 * (تاریخ‌ها با قوانین «تنظیمات ارسال» محاسبه می‌شوند؛ shipping_slots فقط استثناها را نگه می‌دارد)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('shipping_slot_id');
            $table->index('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_date']);
            $table->dropColumn('delivery_date');
        });
    }
};
