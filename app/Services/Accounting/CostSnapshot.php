<?php

namespace App\Services\Accounting;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * ذخیره قیمت خرید واریانت روی قلم فروش (سفارش / فاکتور / مارکت‌پلیس) در لحظه ایجاد
 * تا گزارش سود با تغییر بعدی cost_price عوض نشود.
 * تا وقتی migration ستون cost_price اجرا نشده، کاری انجام نمی‌دهد (ثبت سفارش نباید خطا بدهد).
 */
class CostSnapshot
{
    protected static array $hasColumn = [];

    public static function fill(Model $item, string $variantColumn = 'variant_id'): void
    {
        $table = $item->getTable();

        static::$hasColumn[$table] ??= Schema::hasColumn($table, 'cost_price');

        if (! static::$hasColumn[$table] || $item->getAttribute('cost_price') !== null) {
            return;
        }

        $variantId = $item->getAttribute($variantColumn);

        if (! $variantId) {
            return;
        }

        $item->setAttribute('cost_price', ProductVariant::withTrashed()->whereKey($variantId)->value('cost_price'));
    }
}
