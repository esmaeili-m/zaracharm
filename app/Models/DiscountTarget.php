<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscountTarget extends Model
{
    protected $fillable = [
        'discount_id',
        'target_id',
        'target_type',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
