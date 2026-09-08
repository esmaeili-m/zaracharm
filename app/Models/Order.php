<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Order extends Model
{


    protected $fillable=[

        'user_id',
        'order_number',
        'status',
        'payment_status',
        'subtotal',
        'discount_amount',
        'shipping_amount',
        'expires_at',
        'total_amount'

    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }



    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
