<?php

namespace App\Models;

use App\Enums\SpecificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


class Specification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'type',
        'is_filterable',
        'is_visible',
        'sort',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'is_visible' => 'boolean',
            'is_filterable' => 'boolean',
        ];
    }

    public function getValueAttribute()
    {
        if (!$this->pivot) {
            return null;
        }

        return match ($this->type) {

            SpecificationType::Text->value =>
            $this->pivot->text_value,

            SpecificationType::Number->value =>
            $this->pivot->number_value,

            SpecificationType::Boolean->value =>
            $this->pivot->boolean_value ? 'بله' : 'خیر',

            SpecificationType::Date->value =>
            $this->pivot->date_value,

            SpecificationType::Decimal->value =>
            $this->pivot->decimal_value,

            default => null,
        };
    }
    public function values(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }
}
