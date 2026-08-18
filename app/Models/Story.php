<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Story extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'user',
        'avatar',
        'url',
        'duration',
        'link',
        'status',
        'sort',
    ];

    protected $casts = [
        'duration' => 'integer',
        'status' => 'boolean',
        'sort' => 'integer',
    ];

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
