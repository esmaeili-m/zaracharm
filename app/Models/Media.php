<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    protected $guarded=[];
    protected $casts = [
        'type' => MediaType::class,
        'meta' => 'array',
    ];
    public function isExternal(): bool
    {
        return !empty($this->external_url);
    }
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
