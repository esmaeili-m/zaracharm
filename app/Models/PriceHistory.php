<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $fillable = ['product_id', 'product_variant_id', 'price', 'final_price', 'recorded_on'];

    protected $casts = [
        'price' => 'integer',
        'final_price' => 'integer',
        'recorded_on' => 'date',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
