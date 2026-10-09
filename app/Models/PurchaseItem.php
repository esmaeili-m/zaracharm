<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id', 'variant_id', 'product_name', 'variant_name',
        'quantity', 'unit_cost', 'total_cost', 'stock_added',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'integer',
        'total_cost' => 'integer',
        'stock_added' => 'integer',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
