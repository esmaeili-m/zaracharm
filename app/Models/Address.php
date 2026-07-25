<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{

    protected $fillable = [

        'user_id',
        'title',
        'receiver_name',
        'receiver_phone',
        'province',
        'city',
        'address',
        'plate',
        'unit',
        'postal_code',
        'latitude',
        'longitude',
        'is_default'

    ];



    protected $casts = [

        'is_default' => 'boolean',

        'latitude' => 'decimal:7',

        'longitude' => 'decimal:7',

    ];



    public function user()
    {
        return $this->belongsTo(User::class);
    }



    public function orders()
    {
        return $this->hasMany(Order::class);
    }

}
