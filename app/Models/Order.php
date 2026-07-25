<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'total_amount'

    ];


    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }



    public function payment()
    {
        return $this->hasOne(Payment::class);
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
