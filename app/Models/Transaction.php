<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'category',
        'amount',
        'balance_after',
        'status',
        'description',
        'payment_id',
        'order_id',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }


    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }


    public function reference(): MorphTo
    {
        return $this->morphTo();
    }


    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }


    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }
}
