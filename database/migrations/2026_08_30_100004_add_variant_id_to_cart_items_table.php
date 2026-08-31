<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();
        });

        // بک‌فیل: variant_id را از داخل JSON ستون attributes استخراج و در ستون جدید ذخیره می‌کند
        DB::table('cart_items')->whereNotNull('attributes')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                $attrs = json_decode($row->attributes, true);

                if (is_string($attrs)) {
                    // بعضی رکوردها دوبار json_encode شده‌اند (دیده شد در order_items نمونه)
                    $attrs = json_decode($attrs, true);
                }

                if (is_array($attrs) && !empty($attrs['variant_id'])) {
                    DB::table('cart_items')
                        ->where('id', $row->id)
                        ->update(['variant_id' => $attrs['variant_id']]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_id');
        });
    }
};
