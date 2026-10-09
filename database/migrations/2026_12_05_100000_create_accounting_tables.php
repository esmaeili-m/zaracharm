<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حسابداری ساده (تک‌طرفه)
 *  - accounting_accounts   : صندوق / حساب‌های بانکی
 *  - accounting_categories : دسته‌بندی هزینه و درآمد (affects_profit = در سود و زیان حساب شود)
 *  - suppliers / purchases / purchase_items : خرید کالا از تأمین‌کننده
 *  - accounting_entries    : دفتر دریافت و پرداخت (دستی + خودکار از پرداخت‌ها و فاکتورها)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type', 20)->default('bank'); // cash | bank | other
            $table->string('account_number', 64)->nullable();
            // پرداخت‌های کارت‌به‌کارت به این کارت / پرداخت‌های این درگاه به این حساب واریز می‌شوند
            $table->foreignId('bank_card_id')->nullable()->constrained('bank_cards')->nullOnDelete();
            $table->foreignId('payment_gateway_id')->nullable()->constrained('payment_gateways')->nullOnDelete();
            $table->bigInteger('opening_balance')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // income | expense
            $table->string('title');
            $table->boolean('affects_profit')->default(true);
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('contact_name')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 1000)->nullable();
            // مانده اول دوره؛ مثبت = بدهی ما به تأمین‌کننده
            $table->bigInteger('opening_balance')->default(0);
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft | received | cancelled
            $table->date('purchase_date');
            $table->string('supplier_invoice_number')->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->boolean('update_cost_price')->default(true);
            $table->text('note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('total_cost');
            // مقداری که واقعاً به انبار اضافه شده (برای برگشت در لغو)
            $table->unsignedInteger('stock_added')->default(0);
            $table->timestamps();
        });

        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // income | expense | transfer
            // manual | payment | payment_refund | invoice | invoice_refund | supplier_payment
            $table->string('source', 30)->default('manual');
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('accounting_categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('entry_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'entry_date']);
            // هر پرداخت/فاکتور فقط یک‌بار ثبت خودکار شود
            $table->unique(['source', 'reference_type', 'reference_id'], 'accounting_entries_source_reference_unique');
        });

        $now = now();

        DB::table('accounting_accounts')->insert([
            'title' => 'صندوق',
            'type' => 'cash',
            'opening_balance' => 0,
            'is_default' => true,
            'status' => true,
            'sort' => 1,
            'description' => 'حساب پیش‌فرض؛ فروش حضوری و پرداخت‌هایی که حساب مشخصی ندارند به این حساب ثبت می‌شوند.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $categories = [
            ['expense', 'اجاره', true],
            ['expense', 'حقوق و دستمزد', true],
            ['expense', 'تبلیغات و بازاریابی', true],
            ['expense', 'ارسال و بسته‌بندی', true],
            ['expense', 'قبوض، اینترنت و هاست', true],
            ['expense', 'کارمزد بانک و درگاه', true],
            ['expense', 'کمیسیون مارکت‌پلیس', true],
            ['expense', 'سایر هزینه‌ها', true],
            ['expense', 'برداشت مالک', false],
            ['income', 'درآمد متفرقه', true],
            ['income', 'تسویه مارکت‌پلیس', false],
            ['income', 'آورده / سرمایه', false],
        ];

        DB::table('accounting_categories')->insert(collect($categories)->map(fn ($c, $i) => [
            'type' => $c[0],
            'title' => $c[1],
            'affects_profit' => $c[2],
            'status' => true,
            'sort' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        // دسترسی‌های جدید (accounting.* و purchases.*) برای نقش admin — غیرمخرب و idempotent
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            \App\Support\Permissions::sync();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entries');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('accounting_categories');
        Schema::dropIfExists('accounting_accounts');
    }
};
