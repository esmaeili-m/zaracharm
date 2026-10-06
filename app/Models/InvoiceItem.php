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
