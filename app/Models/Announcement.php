<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * اطلاعیه سایت (نمایش در سکشن «اطلاعیه‌ها»ی صفحه‌ساز)
 * فعال + در بازه starts_at تا ends_at (هر کدام خالی = بدون محدودیت)
 */
class Announcement extends Model
{
    protected $fillable = [
        'title', 'content', 'type', 'link', 'link_text',
        'is_active', 'is_pinned', 'starts_at', 'ends_at', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_pinned' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public const TYPES = [
        'info' => ['title' => 'اطلاع‌رسانی', 'color' => 'info', 'icon' => 'ri-information-line'],
        'success' => ['title' => 'خبر خوب / موفقیت', 'color' => 'success', 'icon' => 'ri-checkbox-circle-line'],
        'warning' => ['title' => 'هشدار', 'color' => 'warning', 'icon' => 'ri-error-warning-line'],
        'danger' => ['title' => 'مهم / فوری', 'color' => 'danger', 'icon' => 'ri-alarm-warning-line'],
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** اطلاعیه‌های قابل نمایش در سایت */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** scheduled | active | expired | inactive */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'inactive',
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at && $this->ends_at->isPast() => 'expired',
            default => 'active',
        };
    }

    /** لینک امن: فقط http(s)، مسیر داخلی یا لنگر */
    public function safeLink(): ?string
    {
        $link = trim((string) $this->link);

        return $link !== '' && preg_match('#^(https?://|/|\#)#i', $link) ? $link : null;
    }
}
