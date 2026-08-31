<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // گسترش enum روش پرداخت (نیاز به raw SQL دارد چون Laravel Schema Builder
        // امکان ویرایش مستقیم enum را بدون doctrine/dbal ندارد)
        DB::statement("ALTER TABLE `payments` MODIFY `method` ENUM('wallet','gateway','installment','cod','transfer') NOT NULL");

        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('gateway_response');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });

        DB::statement("ALTER TABLE `payments` MODIFY `method` ENUM('wallet','gateway','installment') NOT NULL");
    }
};
