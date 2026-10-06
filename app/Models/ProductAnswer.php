<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAnswer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_question_id',
        'user_id',
        'body',
        'is_official',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ProductQuestion::class, 'product_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    // نام نمایشی پاسخ‌دهنده در سایت
    public function getAuthorNameAttribute(): string
    {
        if (!$this->user) {
            return 'کاربر';
        }

        return trim($this->user->full_name) ?: ($this->user->name ?? 'کاربر');
    }
}
