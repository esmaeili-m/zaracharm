<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SmsAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip'];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public const ACTIONS = [
        'template.create' => 'ایجاد الگو',
        'template.update' => 'ویرایش الگو',
        'template.submit' => 'ارسال الگو برای ثبت',
        'template.sync' => 'همگام‌سازی الگوها',
        'template.delete' => 'حذف الگو',
        'template.status_map' => 'تنظیم نگاشت وضعیت الگو',
        'batch.create' => 'شروع ارسال',
        'batch.cancel' => 'لغو ارسال',
        'batch.retry' => 'تلاش مجدد',
        'contacts.import' => 'واردکردن مخاطبان',
        'contacts.export' => 'خروجی مخاطبان',
        'contacts.delete' => 'حذف مخاطب',
        'suppression.add' => 'افزودن به فهرست مسدود',
        'suppression.remove' => 'حذف از فهرست مسدود',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
