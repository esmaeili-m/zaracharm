<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{


    protected $casts=[
        'gateway_response'=>'array'
    ];


    public function order()
    {
        return $this->belongsTo(Order::class);
    }


}
