<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{


    protected $fillable = [

        'order_id',
        'method',
        'carrier',
        'tracking_code',
        'status',
        'sent_at',
        'delivered_at'

    ];



    protected $casts = [

        'sent_at' => 'datetime',

        'delivered_at' => 'datetime',

    ];



    public function order()
    {
        return $this->belongsTo(Order::class);
    }



}
