<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalAccount extends Model
{
    protected $fillable = [
        'user_id',
        'iban',
        'account_holder_name',
        'is_verified',
        'is_default',
        'verified_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_default' => 'boolean',
        'verified_at' => 'datetime',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    /**
     * شماره شبا را برای نمایش به فرمت مناسب برمی‌گرداند.
     */
    public function getFormattedIbanAttribute(): string
    {
        return 'IR' . substr($this->iban, 2);
    }
}
