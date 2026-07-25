<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{

    protected $fillable = [

        'order_id',
        'variant_id',
        'product_name',
        'quantity',
        'price',
        'total_price',
        'attributes'

    ];


    protected $casts = [

        'attributes' => 'array',

    ];



    public function order()
    {
        return $this->belongsTo(Order::class);
    }



    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }


}
