<?php

namespace App\Support\Sections;

use App\Models\Announcement;

/**
 * سکشن «اطلاعیه‌های سایت» صفحه‌ساز (key = announcements)
 *
 * data: style (bar | list | cards)، limit، types[] (خالی = همه)، show_date، dismissible، description
 */
class Announcements
{
    public const KEY = 'announcements';

    public const STYLES = [
        'bar' => 'نوار باریک چرخشی (بالای صفحه)',
        'list' => 'فهرست کادرهای رنگی',
        'cards' => 'کارت‌های شبکه‌ای',
    ];

    public static function defaults(): array
    {
        return [
            'style' => 'list',
            'limit' => 5,
            'types' => [],
            'show_date' => true,
            'dismissible' => true,
            'description' => null,
        ];
    }

    public static function rules(): array
    {
        return [
            'formData.style' => ['required', 'in:' . implode(',', array_keys(self::STYLES))],
            'formData.limit' => ['required', 'integer', 'between:1,20'],
            'formData.types' => ['nullable', 'array'],
            'formData.types.*' => ['in:' . implode(',', array_keys(Announcement::TYPES))],
            'formData.show_date' => ['boolean'],
            'formData.dismissible' => ['boolean'],
            'formData.description' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function messages(): array
    {
        return [
            'formData.style.in' => 'نوع نمایش اطلاعیه‌ها نامعتبر است.',
            'formData.limit.between' => 'تعداد اطلاعیه‌ها باید بین ۱ تا ۲۰ باشد.',
            'formData.types.*.in' => 'نوع اطلاعیه انتخاب‌شده نامعتبر است.',
            'formData.description.max' => 'توضیح نباید بیشتر از ۲۰۰ کاراکتر باشد.',
        ];
    }
}
