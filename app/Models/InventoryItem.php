<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'inventory_id',
        'product_variant_id',
        'quantity',
        'reserved_quantity',
        'minimum_quantity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status'=>'boolean',
        ];
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class,'product_variant_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }
}
