<?php

namespace App\Models;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

}
