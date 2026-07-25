<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'subject',
        'message',
        'type',
        'status',
        'admin_reply',
        'replied_at',
        'ip',
        'user_agent',
    ];
}
