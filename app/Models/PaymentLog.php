<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['payment_id', 'event', 'from_status', 'to_status', 'message', 'data', 'user_id', 'ip'];

    protected $casts = ['data' => 'array'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
