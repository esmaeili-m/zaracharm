<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

/**
 * اتصال به مارکت‌پلیس‌ها (دیجی‌کالا، ترب، باسلام، ...)
 *
 * - marketplaces              : هر مارکت‌پلیس (Provider + Credentials رمزنگاری‌شده + تنظیمات + وضعیت اتصال/آخرین Sync)
 * - marketplace_listings      : اتصال واریانت فروشگاه به محصول/آگهی مارکت‌پلیس
 * - marketplace_orders(+items): سفارش‌های دریافتی از مارکت‌پلیس (یکتا با external_id)
 * - marketplace_sync_logs     : لاگ درخواست/پاسخ/خطا و عملیات همگام‌سازی
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplaces', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->unique();     // digikala | torob | basalam | ...
            $table->string('title');
            $table->text('credentials')->nullable();      // encrypted:array
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('auto_sync')->default(true);   // همگام‌سازی خودکار موجودی/قیمت و دریافت زمان‌بندی‌شده سفارش
            $table->string('webhook_secret', 64)->nullable();

            $table->string('connection_status', 20)->default('unknown'); // unknown | connected | failed
            $table->string('connection_message')->nullable();
            $table->timestamp('connection_checked_at')->nullable();

            $table->timestamp('last_product_sync_at')->nullable();
            $table->timestamp('last_stock_sync_at')->nullable();
            $table->timestamp('last_order_sync_at')->nullable();
            $table->string('order_cursor')->nullable();     // مکان‌نمای دریافت سفارش (در صورت پشتیبانی API)
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();

            $table->string('external_id', 100)->nullable();          // شناسه محصول/آگهی در مارکت‌پلیس
            $table->string('external_variant_id', 100)->nullable();  // شناسه تنوع (در صورت وجود)
            $table->string('external_sku', 100)->nullable();
            $table->string('external_url')->nullable();

            $table->boolean('is_active')->default(true);   // همگام‌سازی این اتصال
            $table->boolean('sync_content')->default(true); // نام، توضیحات، تصاویر
            $table->boolean('sync_price')->default(true);
            $table->boolean('sync_stock')->default(true);

            $table->string('sync_status', 20)->default('pending'); // pending | synced | failed
            $table->unsignedInteger('synced_price')->nullable();
            $table->integer('synced_stock')->nullable();
            $table->boolean('synced_active')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_id', 'product_variant_id']);
            $table->index(['marketplace_id', 'external_id']);
            $table->index(['marketplace_id', 'sync_status']);
        });

        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 100);
            $table->string('external_order_id', 100)->nullable();   // شناسه سفارش مادر (مثلاً سفارش باسلام برای مرسوله)
            $table->string('external_status', 100)->nullable();
            $table->string('status', 20)->default('new');            // new | processing | shipped | delivered | cancelled | returned
            $table->string('payment_status', 20)->default('paid');    // paid | unpaid | refunded (پرداخت در مارکت‌پلیس)

            $table->string('customer_name')->nullable();
            $table->string('customer_mobile', 20)->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();

            $table->unsignedBigInteger('items_amount')->default(0);
            $table->unsignedBigInteger('shipping_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('tracking_code', 100)->nullable();

            $table->string('stock_status', 20)->default('pending');  // pending | applied | partial | reverted | skipped
            $table->text('admin_note')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            // جلوگیری از ثبت سفارش تکراری
            $table->unique(['marketplace_id', 'external_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('marketplace_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('external_item_id', 100)->nullable();
            $table->string('external_product_id', 100)->nullable();
            $table->string('external_variant_id', 100)->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedInteger('stock_deducted')->default(0);
            $table->json('allocations')->nullable();   // [{inventory_item_id, quantity}] برای بازگرداندن موجودی
            $table->timestamps();
        });

        Schema::create('marketplace_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('marketplace_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation', 40);          // connection | listing.create | listing.update | stock | orders.pull | order.status | webhook | feed | ...
            $table->string('direction', 10)->default('out'); // out | in
            $table->string('status', 20);             // success | failed | skipped
            $table->string('method', 10)->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('message', 500)->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('retryable')->default(false);
            $table->timestamp('retried_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['marketplace_id', 'created_at']);
            $table->index(['status', 'operation']);
        });

        $now = now();
        foreach ([
            ['provider' => 'digikala', 'title' => 'دیجی‌کالا'],
            ['provider' => 'basalam', 'title' => 'باسلام'],
            ['provider' => 'torob', 'title' => 'ترب'],
        ] as $row) {
            DB::table('marketplaces')->insert($row + [
                'is_active' => false,
                'auto_sync' => true,
                'webhook_secret' => Str::random(40),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_sync_logs');
        Schema::dropIfExists('marketplace_order_items');
        Schema::dropIfExists('marketplace_orders');
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('marketplaces');
    }
};
