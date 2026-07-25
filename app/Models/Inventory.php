<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'address',
        'phone',
        'sort',
        'status'
    ];

    protected function casts(): array
    {
        return [
            'status'=>'boolean'
        ];
    }

    public function items()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
