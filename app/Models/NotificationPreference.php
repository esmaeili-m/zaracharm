<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'order_updates',
        'payment_updates',
        'shipping_updates',
        'wallet_updates',
        'promotions',
        'newsletter',
        'security_alerts',
    ];

    protected $casts = [
        'order_updates' => 'boolean',
        'payment_updates' => 'boolean',
        'shipping_updates' => 'boolean',
        'wallet_updates' => 'boolean',
        'promotions' => 'boolean',
        'newsletter' => 'boolean',
        'security_alerts' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
