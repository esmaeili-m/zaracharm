<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankCard extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'bank_name', 'owner_name', 'card_number', 'sheba', 'is_active', 'sort'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('id');
    }

    // نمایش چهاررقمی: 6037 - 9918 - 1234 - 5678
    public function getFormattedNumberAttribute(): string
    {
        return implode(' - ', str_split((string) $this->card_number, 4));
    }

    public function getMaskedNumberAttribute(): string
    {
        $number = (string) $this->card_number;

        return substr($number, 0, 6) . str_repeat('*', max(0, strlen($number) - 10)) . substr($number, -4);
    }
}
