<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSpecification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'specification_id',
        'text_value',
        'number_value',
        'decimal_value',
        'boolean_value',
        'date_value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'number_value' => 'integer',
            'decimal_value' => 'decimal:2',
            'boolean_value' => 'boolean',
            'date_value' => 'date',
            'status' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function specification(): BelongsTo
    {
        return $this->belongsTo(Specification::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }
}
