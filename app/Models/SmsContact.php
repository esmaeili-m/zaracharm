<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * مخاطب دستی پیامک (مستقل از کاربران سایت؛ user_id اختیاری)
 */
class SmsContact extends Model
{
    protected $fillable = ['name', 'mobile', 'user_id', 'note', 'marketing_consent'];

    protected $casts = [
        'marketing_consent' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(SmsContactList::class, 'sms_contact_list_members')->withTimestamps();
    }
}
