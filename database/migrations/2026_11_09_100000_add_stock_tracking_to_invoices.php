<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * هماهنگی فاکتور با موجودی انبار
 *
 * - فاکتور دستی/حضوری: order_id و user_id اختیاری + اطلاعات مشتری حضوری
 * - هر قلم فاکتور ثبت می‌کند چه مقدار از کدام انبار «رزرو» یا «کسر» کرده است
 *   (stock_reserved / stock_deducted) تا هر عملیات فقط اختلاف را اعمال کند (idempotent)
 * - stock_movements: دفتر کل تغییرات موجودی (منبع قابل اعتماد برای ممیزی)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->change();

            // online = ساخته‌شده از سفارش سایت | manual = صدور توسط مدیر | pos = فروش حضوری
            $table->string('source')->default('online')->after('invoice_number');
            $table->string('customer_name')->nullable()->after('source');
            $table->string('customer_mobile', 20)->nullable()->after('customer_name');
            $table->text('note')->nullable()->after('total_amount');

            $table->foreignId('created_by')
                ->nullable()
                ->after('note')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['source', 'status']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('inventory_id')
                ->nullable()
                ->after('variant_id')
                ->constrained('inventories')
                ->nullOnDelete();

            // مقدار فعلی رزروشده / کسرشده‌ی همین قلم از انبار
            $table->unsignedInteger('stock_reserved')->default(0)->after('quantity');
            $table->unsignedInteger('stock_deducted')->default(0)->after('stock_reserved');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->foreignId('inventory_id')
                ->constrained('inventories')
                ->cascadeOnDelete();

            // reserve | release | commit | sale | sale_reversal | adjustment
            $table->string('type');

            // تغییر علامت‌دار quantity و reserved_quantity
            $table->integer('quantity_change')->default(0);
            $table->integer('reserved_change')->default(0);

            // وضعیت ردیف انبار بعد از این حرکت
            $table->unsignedInteger('quantity_after');
            $table->unsignedInteger('reserved_after');

            // فاکتور / سفارش / ... + قلم مرتبط
            $table->nullableMorphs('reference');
            $table->unsignedBigInteger('reference_line_id')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['product_variant_id', 'created_at']);
        });

        $this->backfillOnlineReservations();
    }

    /**
     * رزروهای قبلی سفارش‌های سایت (reserved_quantity) به قلم فاکتور متناظر نسبت داده می‌شوند
     * تا بعداً به‌درستی آزاد یا قطعی شوند. فقط وقتی ردیف انبار به‌اندازه کافی رزرو داشته باشد.
     */
    protected function backfillOnlineReservations(): void
    {
        $items = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNotNull('invoices.order_id')
            ->whereNotNull('invoice_items.variant_id')
            ->where('invoice_items.stock_reserved', 0)
            ->where('invoice_items.stock_deducted', 0)
            ->orderBy('invoice_items.id')
            ->select('invoice_items.id', 'invoice_items.variant_id', 'invoice_items.quantity')
            ->get();

        // سقف رزروی که می‌توان به اقلام نسبت داد (برای هر ردیف انبار)
        $remaining = [];

        foreach ($items as $item) {
            $rows = DB::table('inventory_items')
                ->where('product_variant_id', $item->variant_id)
                ->whereNull('deleted_at')
                ->where('reserved_quantity', '>', 0)
                ->orderByDesc('reserved_quantity')
                ->get(['id', 'inventory_id', 'reserved_quantity']);

            foreach ($rows as $row) {
                $remaining[$row->id] ??= (int) $row->reserved_quantity;

                if ($remaining[$row->id] >= (int) $item->quantity) {
                    $remaining[$row->id] -= (int) $item->quantity;

                    DB::table('invoice_items')->where('id', $item->id)->update([
                        'inventory_id' => $row->inventory_id,
                        'stock_reserved' => (int) $item->quantity,
                    ]);

                    break;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_id');
            $table->dropColumn(['stock_reserved', 'stock_deducted']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['source', 'status']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['source', 'customer_name', 'customer_mobile', 'note']);
        });
    }
};
