<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سیستم پرداخت ماژولار
 *
 * - payment_methods  : روش‌های پرداخت (فعال/غیرفعال، ترتیب) — منطق هر روش در کلاس Driver (config/payments.php)
 * - payment_gateways : درگاه‌های بانکی (Provider + Credentials رمزنگاری‌شده + پیش‌فرض)
 * - bank_cards       : کارت‌های مقصد کارت‌به‌کارت
 * - payments         : گسترش جدول پرداخت‌ها (وضعیت/روش رشته‌ای، authority، کد رهگیری، بررسی ادمین)
 * - payment_logs     : لاگ تغییرات مهم هر پرداخت
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // gateway | cod | wallet | transfer | ...
            $table->string('title');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('provider');               // zarinpal | ...
            $table->string('title');
            $table->text('credentials')->nullable();  // encrypted:array
            $table->json('settings')->nullable();     // sandbox, description, ...
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort']);
        });

        Schema::create('bank_cards', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('bank_name');
            $table->string('owner_name');
            $table->string('card_number', 16);
            $table->string('sheba', 26)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // enum => string تا روش/وضعیت جدید بدون migration اضافه شود (جدول در حال حاضر خالی است)
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 30)->change();
            $table->string('status', 20)->default('pending')->change();
        });

        DB::table('payments')->where('status', 'success')->update(['status' => 'paid']);

        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');

            $table->foreignId('user_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->foreignId('gateway_id')->nullable()->after('gateway')->constrained('payment_gateways')->nullOnDelete();
            $table->foreignId('bank_card_id')->nullable()->after('gateway_id')->constrained('bank_cards')->nullOnDelete();

            // درگاه: شناسه درخواست (Authority) | کارت‌به‌کارت/درگاه: کد رهگیری (Ref ID)
            $table->string('authority', 100)->nullable()->unique()->after('transaction_id');
            $table->string('reference', 100)->nullable()->after('authority');
            $table->string('card_pan', 32)->nullable()->after('reference');

            // اطلاعات واریز کارت‌به‌کارت که مشتری وارد می‌کند
            $table->string('payer_name')->nullable()->after('card_pan');
            $table->string('payer_card', 4)->nullable()->after('payer_name');
            $table->timestamp('payer_paid_at')->nullable()->after('payer_card');

            $table->string('failure_reason')->nullable()->after('status');
            $table->text('admin_note')->nullable()->after('failure_reason');
            $table->foreignId('reviewed_by')->nullable()->after('admin_note')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->json('meta')->nullable()->after('gateway_response');

            // جلوگیری از ثبت دوباره یک کد رهگیری در یک روش پرداخت
            $table->unique(['method', 'reference']);
            $table->index(['status', 'method']);
        });

        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('event', 50);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('message')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
        });

        $now = now();
        DB::table('payment_methods')->insert([
            ['key' => 'gateway', 'title' => 'درگاه بانکی (آنلاین)', 'description' => 'پرداخت امن با تمام کارت‌های عضو شتاب', 'is_active' => true, 'sort' => 1, 'settings' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'wallet', 'title' => 'کیف پول', 'description' => 'پرداخت از موجودی کیف پول', 'is_active' => true, 'sort' => 2, 'settings' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'transfer', 'title' => 'کارت به کارت', 'description' => 'واریز به کارت فروشگاه و ثبت کد رهگیری', 'is_active' => true, 'sort' => 3, 'settings' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'cod', 'title' => 'پرداخت در محل', 'description' => 'نقدی یا با کارت‌خوان هنگام تحویل', 'is_active' => false, 'sort' => 4, 'settings' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['method', 'reference']);
            $table->dropIndex(['status', 'method']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('gateway_id');
            $table->dropConstrainedForeignId('bank_card_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropUnique(['uuid']);
            $table->dropUnique(['authority']);
            $table->dropColumn([
                'uuid', 'authority', 'reference', 'card_pan', 'payer_name', 'payer_card', 'payer_paid_at',
                'failure_reason', 'admin_note', 'reviewed_at', 'meta',
            ]);
        });

        Schema::dropIfExists('bank_cards');
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('payment_methods');
    }
};
