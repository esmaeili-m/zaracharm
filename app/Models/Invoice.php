<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $guarded=[];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ======================
    // آیتم‌های فاکتور
    // ======================
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ======================
    // پرداخت‌ها
    // (درگاه / کیف پول / retry)
    // ======================
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // ======================
    // آخرین پرداخت موفق
    // ======================
    public function successfulPayment()
    {
        return $this->hasOne(Payment::class)
            ->where('status', 'success')
            ->latestOfMany();
    }

    // ======================
    // جمع قیمت آیتم‌ها (computed)
    // ======================
    public function getItemsTotalAttribute()
    {
        return $this->items->sum('total');
    }

    // ======================
    // آیا پرداخت شده؟
    // ======================
    public function getIsPaidAttribute()
    {
        return $this->status === 'paid';
    }
}
