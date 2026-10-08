<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ویژگی تعریف‌شده برای یک دسته‌بندی (منبع فیلترهای صفحه دسته‌بندی)
 * attribute_type: spec (Specification) | option (Option)
 */
class CategoryAttribute extends Model
{
    public const SPEC = 'spec';
    public const OPTION = 'option';

    protected $fillable = ['category_id', 'attribute_type', 'attribute_id', 'is_filter', 'sort'];

    protected $casts = [
        'is_filter' => 'boolean',
        'sort' => 'integer',
        'attribute_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function specification(): BelongsTo
    {
        return $this->belongsTo(Specification::class, 'attribute_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'attribute_id');
    }

    /** @return Specification|Option|null */
    public function attribute()
    {
        return $this->attribute_type === self::SPEC ? $this->specification : $this->option;
    }
}
