<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PageSection extends Model
{
    protected $guarded=[];
    protected $casts =[
      'data' => 'json'
    ];
    public function section()
    {
        return $this->belongsTo(Section::class);
    }
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    public function getMediaByCollection(string $collection)
    {
        return $this->media()
            ->where('collection', $collection)
            ->first();
    }
}
