<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{


    protected $fillable=[

        'user_id',
        'order_number',
        'status',
        'payment_status',
        'subtotal',
        'discount_amount',
        'shipping_amount',
        'expires_at',
        'total_amount',
        // بدون این‌ها update() در Checkout آدرس، روز ارسال و روش پرداخت را ذخیره نمی‌کرد
        'coupon_id',
        'tax_amount',
        'address_id',
        'shipping_slot_id',
        'delivery_date',
        'payment_method',

    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'delivery_date' => 'date',
    ];
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
    public function shippingSlot(): BelongsTo
    {
        return $this->belongsTo(ShippingSlot::class);
    }
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }

    // فاکتور سفارش (Checkout مبلغ ارسال/کل را روی آن هم بروز می‌کند)
    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }



    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
