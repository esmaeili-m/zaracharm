<?php

namespace App\Models;

use App\Services\Sms\TemplateBody;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * الگوی پیامک خط خدماتی اشتراکی ملی پیامک
 *
 * status فقط از روی BodyStatus واقعی سرویس (و نگاشت تنظیم‌شده در صفحه الگوها) تعیین می‌شود؛
 * فقط الگوی approved برای ارسال قابل انتخاب است.
 */
class SmsTemplate extends Model
{
    protected $fillable = [
        'title', 'body', 'variables', 'category', 'description',
        'provider_body_id', 'provider_status', 'provider_description', 'provider_created_at',
        'status', 'last_error', 'submitted_at', 'last_checked_at', 'created_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'submitted_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public const STATUSES = [
        'draft' => ['title' => 'پیش‌نویس (ثبت‌نشده)', 'color' => 'secondary'],
        'pending' => ['title' => 'در انتظار بررسی', 'color' => 'warning'],
        'approved' => ['title' => 'تأییدشده', 'color' => 'success'],
        'rejected' => ['title' => 'ردشده', 'color' => 'danger'],
        'unknown' => ['title' => 'وضعیت نامشخص', 'color' => 'info'],
        'failed' => ['title' => 'خطا در ثبت', 'color' => 'danger'],
        'missing' => ['title' => 'در پنل یافت نشد', 'color' => 'dark'],
    ];

    public const CATEGORIES = [
        'service' => 'خدماتی / اطلاع‌رسانی',
        'marketing' => 'تبلیغاتی',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(SmsBatch::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved')->whereNotNull('provider_body_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved' && $this->provider_body_id;
    }

    public function placeholderCount(): int
    {
        return TemplateBody::count($this->body);
    }

    /**
     * تعریف متغیرها به تعداد placeholderهای متن (کمبود با مقدار پیش‌فرض پر می‌شود)
     *
     * @return array<int, array{label: string, source: string}>
     */
    public function variableDefinitions(): array
    {
        $saved = array_values($this->variables ?? []);
        $definitions = [];

        for ($i = 0; $i < $this->placeholderCount(); $i++) {
            $definitions[$i] = [
                'label' => (string) ($saved[$i]['label'] ?? 'متغیر ' . ($i + 1)),
                'source' => (string) ($saved[$i]['source'] ?? 'fixed'),
            ];
        }

        return $definitions;
    }

    public function statusBadge(): array
    {
        return self::STATUSES[$this->status] ?? self::STATUSES['unknown'];
    }
}
