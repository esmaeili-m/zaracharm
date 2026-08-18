<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignReward extends Model
{
    protected $fillable = [
        'campaign_id',
        'reward_type',
        'value',
        'max_value',
        'product_id',
        'product_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_value' => 'decimal:2',
    ];
    public function getRewardTypeNameAttribute(): string
    {
        return match ($this->reward_type) {

            0 => 'درصد تخفیف',

            1 => 'مبلغ ثابت',

            2 => 'ارسال رایگان',

            3 => 'اعتبار کیف پول',

            4 => 'هدیه',

            default => '-',

        };
    }
    public function getValueTextAttribute(): string
    {
        return match ($this->reward_type) {

            0 => $this->value . ' %',

            1 => number_format($this->value) . ' تومان',

            2 => 'رایگان',

            3 => number_format($this->value) . ' تومان',

            4 => $this->value,

            default => $this->value,

        };
    }
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
