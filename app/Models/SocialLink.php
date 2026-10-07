<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'url',
        'icon',
        'is_active',
        'sort',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // لینک‌های قابل نمایش در سایت: فعال، با آدرس http/https، به ترتیب پنل
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->where('url', 'like', 'http://%')->orWhere('url', 'like', 'https://%'))
            ->orderBy('sort')
            ->orderBy('id');
    }

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon ? url('/storage/' . $this->icon) : null;
    }
}
