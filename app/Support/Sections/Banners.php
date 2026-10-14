<?php

namespace App\Support\Sections;

use Illuminate\Support\Str;

/**
 * سکشن «بنرها»ی صفحه‌ساز (key = banners)
 *
 * data: layout (grid | slider)، columns_desktop (1-4)، columns_mobile (1-2)، ratio، gap (sm|md|lg)،
 *       rounded، hover_zoom، show_text، autoplay، items: [{uid, title, subtitle, link, new_tab, alt}]
 * media: collection = banner_desktop / banner_mobile ، purpose = uid آیتم
 */
class Banners
{
    public const KEY = 'banners';

    public const MAX_ITEMS = 8;

    public const DESKTOP = 'banner_desktop';
    public const MOBILE = 'banner_mobile';

    // پوشه فایل‌ها بدون کلمه banner: فایروال برخی هاست‌ها (403) و افزونه‌های تبلیغ‌گیر آدرس‌های حاوی banner را مسدود می‌کنند
    public const DIRECTORY = 'pb-media';

    // نسبت ابعاد (style="aspect-ratio" ، بدون کلاس پویای Tailwind)
    public const RATIOS = [
        'auto' => 'ابعاد واقعی تصویر',
        '4/1' => 'نواری باریک (۴:۱)',
        '3/1' => 'عریض (۳:۱)',
        '21/9' => 'سینمایی (۲۱:۹)',
        '16/9' => 'ویدیویی (۱۶:۹)',
        '4/3' => 'کلاسیک (۴:۳)',
        '1/1' => 'مربع (۱:۱)',
    ];

    public static function newItem(): array
    {
        return ['uid' => Str::lower(Str::random(8)), 'title' => null, 'subtitle' => null, 'link' => null, 'new_tab' => false, 'alt' => null];
    }

    public static function defaults(): array
    {
        return [
            'layout' => 'grid',
            'columns_desktop' => 2,
            'columns_mobile' => 1,
            'ratio' => 'auto',
            'gap' => 'md',
            'rounded' => true,
            'hover_zoom' => true,
            'show_text' => true,
            'autoplay' => true,
            'items' => [self::newItem()],
            'images' => [],
        ];
    }

    /**
     * @param  array  $items  آیتم‌های فعلی فرم
     * @param  array<string>  $existingDesktop  uidهایی که تصویر دسکتاپ ذخیره‌شده دارند
     */
    public static function rules(array $items, array $existingDesktop): array
    {
        $rules = [
            'formData.layout' => ['required', 'in:grid,slider'],
            'formData.columns_desktop' => ['required', 'integer', 'between:1,4'],
            'formData.columns_mobile' => ['required', 'integer', 'between:1,2'],
            'formData.ratio' => ['required', 'in:' . implode(',', array_keys(self::RATIOS))],
            'formData.gap' => ['required', 'in:sm,md,lg'],
            'formData.rounded' => ['boolean'],
            'formData.hover_zoom' => ['boolean'],
            'formData.show_text' => ['boolean'],
            'formData.autoplay' => ['boolean'],
            'formData.items' => ['required', 'array', 'min:1', 'max:' . self::MAX_ITEMS],
            'formData.items.*.uid' => ['required', 'alpha_num', 'size:8', 'distinct'],
            'formData.items.*.title' => ['nullable', 'string', 'max:80'],
            'formData.items.*.subtitle' => ['nullable', 'string', 'max:150'],
            'formData.items.*.link' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/|#)/i'],
            'formData.items.*.new_tab' => ['boolean'],
            'formData.items.*.alt' => ['nullable', 'string', 'max:150'],
        ];

        foreach (array_values($items) as $item) {
            $uid = (string) ($item['uid'] ?? '');

            if (! preg_match('/^[a-z0-9]{8}$/', $uid)) {
                continue;
            }

            // تصویر دسکتاپ برای بنر جدید الزامی است (بنر قبلی تصویرش را دارد)
            $rules["formData.images.desktop_{$uid}"] = [in_array($uid, $existingDesktop, true) ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'];
            $rules["formData.images.mobile_{$uid}"] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'];
        }

        return $rules;
    }

    public static function messages(): array
    {
        return [
            'formData.items.required' => 'حداقل یک بنر اضافه کنید.',
            'formData.items.max' => 'حداکثر ' . self::MAX_ITEMS . ' بنر در هر سکشن مجاز است.',
            'formData.items.*.title.max' => 'عنوان بنر نباید بیشتر از ۸۰ کاراکتر باشد.',
            'formData.items.*.subtitle.max' => 'زیرعنوان بنر نباید بیشتر از ۱۵۰ کاراکتر باشد.',
            'formData.items.*.link.regex' => 'لینک بنر باید با http(s)://، / یا # شروع شود.',
            'formData.items.*.alt.max' => 'متن جایگزین نباید بیشتر از ۱۵۰ کاراکتر باشد.',
            // کلیدهای عمومی (سکشن‌های دیگر هم formData.images.* دارند)
            'formData.images.*.required' => 'تصویر را انتخاب کنید.',
            'formData.images.*.image' => 'فایل انتخاب‌شده باید تصویر باشد.',
            'formData.images.*.mimes' => 'تصویر باید jpg، png، webp یا gif باشد.',
            'formData.images.*.max' => 'حجم تصویر نباید بیشتر از ۵ مگابایت باشد.',
            'formData.ratio.in' => 'نسبت ابعاد نامعتبر است.',
        ];
    }
}
