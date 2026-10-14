<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * پیامک یک گیرنده
 *
 * accepted  : ملی پیامک درخواست را پذیرفت (recId گرفت) — الزاماً به گوشی نرسیده است
 * delivered : گزارش تحویل «رسیده به گوشی»
 * unknown   : نتیجه درخواست معلوم نیست (مثلاً قطع اتصال پس از ارسال)؛ برای جلوگیری از ارسال دوباره، خودکار تکرار نمی‌شود
 */
class SmsMessage extends Model
{
    protected $fillable = [
        'sms_batch_id', 'mobile', 'recipient_name', 'user_id', 'sms_contact_id', 'args',
        'status', 'provider_rec_id', 'provider_code', 'delivery_code', 'error', 'error_permanent',
        'attempts', 'cost', 'sent_at', 'delivered_at', 'delivery_checked_at',
    ];

    protected $casts = [
        'args' => 'array',
        'error_permanent' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_checked_at' => 'datetime',
    ];

    public const STATUSES = [
        'queued' => ['title' => 'در صف', 'color' => 'secondary'],
        'sending' => ['title' => 'در حال ارسال', 'color' => 'primary'],
        'accepted' => ['title' => 'پذیرفته‌شده توسط سرویس', 'color' => 'info'],
        'delivered' => ['title' => 'تحویل‌شده', 'color' => 'success'],
        'undelivered' => ['title' => 'تحویل نشد', 'color' => 'warning'],
        'failed' => ['title' => 'ناموفق', 'color' => 'danger'],
        'unknown' => ['title' => 'وضعیت نامشخص', 'color' => 'dark'],
        'cancelled' => ['title' => 'لغوشده', 'color' => 'light'],
    ];

    // وضعیت‌هایی که درخواست به سرویس رسیده و پذیرفته شده است
    public const SENT = ['accepted', 'delivered', 'undelivered'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SmsBatch::class, 'sms_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(SmsContact::class, 'sms_contact_id');
    }

    public function isRetryable(): bool
    {
        return $this->status === 'failed' && ! $this->error_permanent
            && $this->attempts < (int) config('sms.max_attempts', 3);
    }

    public function statusBadge(): array
    {
        return self::STATUSES[$this->status] ?? ['title' => $this->status, 'color' => 'secondary'];
    }

    /** نمایش شماره با پوشاندن ارقام میانی */
    public function maskedMobile(): string
    {
        return substr($this->mobile, 0, 4) . '***' . substr($this->mobile, -4);
    }
}
