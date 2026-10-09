<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * دفتر کل تغییرات موجودی انبار؛ فقط از طریق InventoryService ساخته می‌شود.
 */
class StockMovement extends Model
{
    public const TYPES = [
        'reserve' => 'رزرو',
        'release' => 'آزادسازی رزرو',
        'commit' => 'قطعی شدن رزرو',
        'sale' => 'کسر از انبار',
        'sale_reversal' => 'بازگشت به انبار',
        'adjustment' => 'اصلاح موجودی',
        'purchase' => 'ورود از خرید',
        'purchase_reversal' => 'لغو خرید',
    ];

    protected $fillable = [
        'inventory_item_id',
        'product_variant_id',
        'inventory_id',
        'type',
        'quantity_change',
        'reserved_change',
        'quantity_after',
        'reserved_after',
        'reference_type',
        'reference_id',
        'reference_line_id',
        'user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'reserved_change' => 'integer',
            'quantity_after' => 'integer',
            'reserved_after' => 'integer',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
