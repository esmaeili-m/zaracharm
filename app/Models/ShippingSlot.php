<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingSlot extends Model
{
    protected $fillable = [
        'date',
        'label',
        'cost',
        'is_holiday',
        'is_active',
    ];

    protected $casts = [
        'date'       => 'date',
        'cost'       => 'integer',
        'is_holiday' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
