<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $guarded=[];
    public function items()
    {
        return $this->hasMany(MenuItem::class)
            ->whereNull('parent_id')
            ->where('status', true)
            ->orderBy('sort');
    }

    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }
    public function children()
    {
        return $this->hasMany(MenuItem::class, 'menu_id')->orderBy('sort');
    }
}
