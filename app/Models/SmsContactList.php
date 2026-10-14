<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SmsContactList extends Model
{
    protected $fillable = ['title', 'description'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(SmsContact::class, 'sms_contact_list_members')->withTimestamps();
    }
}
