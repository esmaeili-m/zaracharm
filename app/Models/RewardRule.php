<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardRule extends Model
{
    protected $fillable = [
        'key',
        'name',
        'points',
        'amount_per_point',
        'description',
        'is_active',
    ];

    protected $casts = [
        'points' => 'integer',
        'amount_per_point' => 'integer',
        'is_active' => 'boolean',
    ];
}
