<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'user_id',
        'body',
        'is_anonymous',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'status' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ProductAnswer::class);
    }

    public function activeAnswers(): HasMany
    {
        return $this->hasMany(ProductAnswer::class)
            ->where('status', true)
            ->orderByDesc('is_official')
            ->oldest();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    // نام نمایشی پرسش‌کننده در سایت
    public function getAuthorNameAttribute(): string
    {
        if ($this->is_anonymous || !$this->user) {
            return 'کاربر ناشناس';
        }

        return trim($this->user->full_name) ?: ($this->user->name ?? 'کاربر');
    }
}
