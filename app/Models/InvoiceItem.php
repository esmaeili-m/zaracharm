<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $guarded=[];

    protected $casts = [
        'quantity' => 'integer',
        'stock_reserved' => 'integer',
        'stock_deducted' => 'integer',
    ];

    // قیمت خرید لحظه فروش برای گزارش سود (App\Services\Accounting\CostSnapshot)
    protected static function booted(): void
    {
        static::creating(fn ($item) => \App\Services\Accounting\CostSnapshot::fill($item, 'variant_id'));
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}
