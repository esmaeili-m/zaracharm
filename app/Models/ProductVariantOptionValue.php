<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariantOptionValue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_variant_id',
        'option_value_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class);
    }
}
