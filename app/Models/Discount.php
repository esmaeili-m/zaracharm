<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Discount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'type',
        'value',
        'maximum_discount',
        'minimum_purchase',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function targets(): HasMany
    {
        return $this->hasMany(DiscountTarget::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }
    protected function startsAt(): Attribute
    {
        return Attribute::make(

            get: fn ($value) => $value
                ? Jalalian::fromCarbon(Carbon::parse($value))->format('Y/m/d')
                : null,

            set: fn ($value) => filled($value)
                ? Jalalian::fromFormat('Y/m/d', $value)
                    ->toCarbon()
                    ->format('Y-m-d H:i:s')
                : null,

        );
    }
    protected function endsAt(): Attribute
    {
        return Attribute::make(

            get: fn ($value) => $value
                ? Jalalian::fromCarbon(Carbon::parse($value))->format('Y/m/d')
                : null,

            set: fn ($value) => filled($value)
                ? Jalalian::fromFormat('Y/m/d', $value)
                    ->toCarbon()
                    ->format('Y-m-d H:i:s')
                : null,

        );
    }
    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }
}
