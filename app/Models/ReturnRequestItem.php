<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestItem extends Model
{
    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'quantity',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
