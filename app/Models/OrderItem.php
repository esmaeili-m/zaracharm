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

    // قیمت خرید لحظه فروش برای گزارش سود (App\Services\Accounting\CostSnapshot)
    protected static function booted(): void
    {
        static::creating(fn ($item) => \App\Services\Accounting\CostSnapshot::fill($item, 'variant_id'));
    }



    public function order()
    {
        return $this->belongsTo(Order::class);
    }



    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnRequestItem::class);
    }



}
