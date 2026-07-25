<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Setting extends Model
{
    protected $guarded=[];
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    public static function logo(): string
    {
        $path = static::where('key', 'logo')
            ->first()
            ?->media()
            ->where('collection', 'logo')
            ->first()
            ?->file_path;

        return $path
            ? asset('storage/' . $path)
            : asset('images/default-logo.png');
    }
    public function getLogoUrlAttribute()
    {
        $logo = $this->media
            ->where('collection', 'logo')
            ->first();

        return $logo
            ? url('/storage/' . $logo->file_path)
            : null;
    }
}
