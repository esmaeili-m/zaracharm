<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * شماره مسدود یا انصراف‌داده
 * scope=all: هیچ پیامکی از این ماژول ارسال نمی‌شود | scope=marketing: فقط پیامک تبلیغاتی ارسال نمی‌شود
 */
class SmsSuppression extends Model
{
    protected $fillable = ['mobile', 'scope', 'reason', 'created_by'];

    public const SCOPES = [
        'all' => 'همه پیامک‌ها',
        'marketing' => 'فقط تبلیغاتی (انصراف)',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
