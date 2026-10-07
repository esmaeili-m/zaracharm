<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gateway_response' => 'array',
        'meta' => 'array',
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'payer_paid_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // شناسه غیرقابل حدس برای آدرس Callback
        static::creating(function (Payment $payment) {
            $payment->uuid ??= (string) Str::uuid();
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gatewayModel()
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id')->withTrashed();
    }

    public function bankCard()
    {
        return $this->belongsTo(BankCard::class)->withTrashed();
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentLog::class)->latest('id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function statusEnum(): ?PaymentStatus
    {
        return PaymentStatus::tryFrom((string) $this->status);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusEnum()?->label() ?? (string) $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending->value;
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid->value;
    }
}
