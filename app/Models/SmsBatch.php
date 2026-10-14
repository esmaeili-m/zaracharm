<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * یک عملیات ارسال (تکی یا گروهی) با نسخه ثابت الگو
 */
class SmsBatch extends Model
{
    protected $fillable = [
        'uuid', 'sms_template_id', 'template_title', 'template_body', 'provider_body_id', 'category',
        'source', 'source_meta', 'status', 'is_fake', 'total', 'skipped', 'skipped_summary',
        'estimated_cost', 'last_error', 'created_by', 'started_at', 'finished_at', 'cancelled_at',
    ];

    protected $casts = [
        'source_meta' => 'array',
        'skipped_summary' => 'array',
        'is_fake' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUSES = [
        'queued' => ['title' => 'در صف', 'color' => 'secondary'],
        'sending' => ['title' => 'در حال ارسال', 'color' => 'primary'],
        'completed' => ['title' => 'پایان‌یافته', 'color' => 'success'],
        'failed' => ['title' => 'متوقف (خطا)', 'color' => 'danger'],
        'cancelled' => ['title' => 'لغوشده', 'color' => 'dark'],
    ];

    public const SOURCES = [
        'single' => 'ارسال تکی',
        'manual' => 'شماره‌های دستی',
        'customers' => 'مشتریان سایت',
        'list' => 'لیست مخاطبان',
        'import' => 'فایل واردشده',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'sms_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['queued', 'sending'], true);
    }

    public function statusBadge(): array
    {
        return self::STATUSES[$this->status] ?? ['title' => $this->status, 'color' => 'secondary'];
    }

    /**
     * شمارش وضعیت پیامک‌ها (یک کوئری)
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return $this->messages()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')
            ->map(fn ($c) => (int) $c)->all();
    }
}
