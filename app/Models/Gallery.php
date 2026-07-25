<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    protected $guarded=[];
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
