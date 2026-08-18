<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignUsage extends Model
{
    protected $fillable = [
        'campaign_id',
        'user_id',
        'invoice_id',
        'discount_amount',
        'quantity',
    ];


    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];


    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
