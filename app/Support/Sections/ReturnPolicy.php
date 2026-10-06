<?php

namespace App\Support\Sections;

/**
 * سکشن «شرایط مرجوعی» صفحه‌ساز (key = returnPolicy)
 * مقادیر پیش‌فرض یک‌جا تعریف شده‌اند تا فرم داشبورد، Frontend و migration از یک منبع استفاده کنند.
 */
class ReturnPolicy
{
    public const KEY = 'returnPolicy';

    // اسلاگ صفحه CMS که لینک «شرایط مرجوعی» فوتر به آن اشاره می‌کند
    public const PAGE_SLUG = 'return-policy';

    // لیست‌های قابل افزودن/حذف در فرم داشبورد و قالب هر آیتم
    public const LISTS = [
        'conditions' => '',
        'exclusions' => '',
        'steps' => ['title' => '', 'text' => ''],
    ];

    public const MAX_ITEMS = 20;

    public static function defaults(): array
    {
        return [
            'description' => 'اگر کالای خریداری‌شده با انتظار شما مطابقت ندارد، طبق شرایط زیر می‌توانید درخواست مرجوعی ثبت کنید.',
            'return_days' => 7,
            'conditions' => [
                'کالا استفاده نشده و در بسته‌بندی اصلی باشد.',
                'برچسب‌ها، پلمپ و متعلقات کالا سالم و کامل باشد.',
                'فاکتور یا شماره سفارش همراه درخواست ارائه شود.',
            ],
            'exclusions' => [
                'کالاهای بهداشتی و آرایشی باز شده',
                'کالاهایی که طبق سفارش مشتری سفارشی‌سازی شده‌اند',
            ],
            'steps' => [
                ['title' => 'ثبت درخواست', 'text' => 'از بخش سفارش‌های من، سفارش موردنظر را انتخاب و درخواست مرجوعی ثبت کنید.'],
                ['title' => 'بررسی درخواست', 'text' => 'کارشناسان ما درخواست شما را بررسی و نتیجه را اطلاع‌رسانی می‌کنند.'],
                ['title' => 'ارسال کالا', 'text' => 'کالا را همراه با تمام متعلقات و بسته‌بندی اصلی ارسال کنید.'],
                ['title' => 'بازگشت وجه', 'text' => 'پس از تأیید کالا، مبلغ به کیف پول یا حساب شما بازگردانده می‌شود.'],
            ],
            'note' => null,
            'show_cta' => true,
        ];
    }

    public static function rules(): array
    {
        return [
            'formData.description' => ['nullable', 'string', 'max:1000'],
            'formData.return_days' => ['required', 'integer', 'min:0', 'max:365'],
            'formData.conditions' => ['nullable', 'array', 'max:' . self::MAX_ITEMS],
            'formData.conditions.*' => ['required', 'string', 'max:255'],
            'formData.exclusions' => ['nullable', 'array', 'max:' . self::MAX_ITEMS],
            'formData.exclusions.*' => ['required', 'string', 'max:255'],
            'formData.steps' => ['nullable', 'array', 'max:' . self::MAX_ITEMS],
            'formData.steps.*.title' => ['required', 'string', 'max:100'],
            'formData.steps.*.text' => ['nullable', 'string', 'max:500'],
            'formData.note' => ['nullable', 'string', 'max:1000'],
            'formData.show_cta' => ['boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'formData.return_days.required' => 'مهلت مرجوعی الزامی است.',
            'formData.return_days.integer' => 'مهلت مرجوعی باید عدد باشد.',
            'formData.return_days.min' => 'مهلت مرجوعی نمی‌تواند منفی باشد.',
            'formData.return_days.max' => 'مهلت مرجوعی نمی‌تواند بیشتر از ۳۶۵ روز باشد.',
            'formData.conditions.max' => 'حداکثر ' . self::MAX_ITEMS . ' شرط قابل ثبت است.',
            'formData.conditions.*.required' => 'متن شرط را وارد کنید یا آن را حذف کنید.',
            'formData.conditions.*.max' => 'هر شرط نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'formData.exclusions.max' => 'حداکثر ' . self::MAX_ITEMS . ' مورد قابل ثبت است.',
            'formData.exclusions.*.required' => 'متن مورد را وارد کنید یا آن را حذف کنید.',
            'formData.exclusions.*.max' => 'هر مورد نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'formData.steps.max' => 'حداکثر ' . self::MAX_ITEMS . ' مرحله قابل ثبت است.',
            'formData.steps.*.title.required' => 'عنوان مرحله را وارد کنید یا آن را حذف کنید.',
            'formData.steps.*.title.max' => 'عنوان مرحله نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.',
            'formData.steps.*.text.max' => 'توضیح مرحله نمی‌تواند بیشتر از ۵۰۰ کاراکتر باشد.',
            'formData.description.max' => 'توضیح نمی‌تواند بیشتر از ۱۰۰۰ کاراکتر باشد.',
            'formData.note.max' => 'یادداشت نمی‌تواند بیشتر از ۱۰۰۰ کاراکتر باشد.',
        ];
    }
}
