<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // بعد از این زمان، اگر سفارش هنوز pending بود، منقضی و لغو می‌شود
            $table->timestamp('expires_at')->nullable()->after('total_amount');
        });

        // اضافه‌کردن وضعیت جدید 'converted' برای سبدهایی که به سفارش تبدیل شده‌اند
        // (متفاوت از 'completed' که یعنی پرداخت هم انجام شده)
        DB::statement("ALTER TABLE `carts` MODIFY `status` ENUM('active','converted','completed','abandoned') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });

        DB::statement("ALTER TABLE `carts` MODIFY `status` ENUM('active','completed','abandoned') NOT NULL DEFAULT 'active'");
    }
};
