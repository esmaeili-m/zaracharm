<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_number',
        'title',
        'status',
        'is_read',
        'last_reply_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    public static function generateNumber(): string
    {
        return 'TK-' . now()->format('Ymd') . '-' . random_int(1000, 9999);
    }
    public function lastMessage()
    {
        return $this->hasOne(TicketMessage::class)->latestOfMany();
    }

}
