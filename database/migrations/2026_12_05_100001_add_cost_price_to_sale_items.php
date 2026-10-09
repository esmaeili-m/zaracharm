<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قیمت خرید (بهای تمام‌شده) هر قلم در لحظه فروش ذخیره می‌شود تا تغییر بعدی cost_price
 * سود دوره‌های گذشته را تغییر ندهد. ردیف‌های قبلی با قیمت خرید فعلی واریانت پر می‌شوند.
 */
return new class extends Migration
{
    protected array $tables = ['order_items' => 'variant_id', 'invoice_items' => 'variant_id', 'marketplace_order_items' => 'product_variant_id'];

    public function up(): void
    {
        foreach ($this->tables as $table => $variantColumn) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'cost_price')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('cost_price')->nullable();
            });

            DB::table($table)
                ->whereNull('cost_price')
                ->whereNotNull($variantColumn)
                ->update([
                    'cost_price' => DB::raw("(select pv.cost_price from product_variants pv where pv.id = {$table}.{$variantColumn})"),
                ]);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'cost_price')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('cost_price'));
            }
        }
    }
};
