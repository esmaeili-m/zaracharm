<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignCondition extends Model
{
    protected $fillable = [
        'campaign_id',
        'condition_type',
        'value',
        'status',
        'operator',
    ];
    public function getConditionTypeNameAttribute()
    {
        return match ($this->condition_type) {
            0 => 'حداقل مبلغ خرید',
            1 => 'حداکثر مبلغ خرید',
            2 => 'نقش کاربر',
            3 => 'اولین خرید',
            4 => 'تعداد محصول',
            default => '-',
        };
    }
    public function getOperatorNameAttribute()
    {
        return match ($this->operator) {
            '=' => 'مساوی',
            '>' => 'بزرگتر از',
            '>=' => 'بزرگتر مساوی',
            '<' => 'کوچکتر از',
            '<=' => 'کوچکتر مساوی',
            default => '-',
        };
    }
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
