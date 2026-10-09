<?php

namespace App\Support\Sections;

/**
 * اطلاعات نمایشی انواع سکشن برای صفحه‌ساز بصری (آیکن، توضیح، گروه، عرض پیشنهادی)
 * کلید = sections.key ؛ سکشن ناشناخته با مقادیر پیش‌فرض نمایش داده می‌شود.
 */
class Catalog
{
    public const GROUPS = [
        'hero' => 'بنر و معرفی',
        'shop' => 'فروشگاه',
        'content' => 'محتوا',
        'contact' => 'ارتباط با مشتری',
    ];

    public const ITEMS = [
        'hero' => ['icon' => 'ri-layout-top-2-line', 'group' => 'hero', 'width' => 12, 'description' => 'بنر اصلی با عنوان، دکمه و تصویر یا ویدیو + انیمیشن'],
        'sliders' => ['icon' => 'ri-slideshow-3-line', 'group' => 'hero', 'width' => 12, 'description' => 'اسلایدر تصاویر تبلیغاتی'],
        'stories' => ['icon' => 'ri-donut-chart-line', 'group' => 'hero', 'width' => 12, 'description' => 'استوری‌های دایره‌ای مثل اینستاگرام'],
        'about' => ['icon' => 'ri-information-line', 'group' => 'hero', 'width' => 12, 'description' => 'معرفی فروشگاه با تصویر و آمار'],

        'products' => ['icon' => 'ri-shopping-bag-3-line', 'group' => 'shop', 'width' => 12, 'description' => 'لیست یا اسلایدر محصولات (جدید، پرفروش، دستی...)'],
        'categories' => ['icon' => 'ri-apps-2-line', 'group' => 'shop', 'width' => 12, 'description' => 'دسته‌بندی‌های محصولات'],
        'instantOffers' => ['icon' => 'ri-flashlight-line', 'group' => 'shop', 'width' => 12, 'description' => 'پیشنهادهای لحظه‌ای / شگفت‌انگیز'],
        'campaigns' => ['icon' => 'ri-megaphone-line', 'group' => 'shop', 'width' => 12, 'description' => 'محصولات یک کمپین تخفیف'],
        'brands' => ['icon' => 'ri-award-line', 'group' => 'shop', 'width' => 12, 'description' => 'لوگو و لیست برندها'],
        'brandProducts' => ['icon' => 'ri-store-3-line', 'group' => 'shop', 'width' => 12, 'description' => 'محصولات یک برند'],

        'articles' => ['icon' => 'ri-article-line', 'group' => 'content', 'width' => 12, 'description' => 'آخرین مقالات بلاگ'],
        'faq' => ['icon' => 'ri-question-answer-line', 'group' => 'content', 'width' => 12, 'description' => 'سوالات متداول (آکاردئونی)'],
        'returnPolicy' => ['icon' => 'ri-arrow-go-back-line', 'group' => 'content', 'width' => 12, 'description' => 'شرایط مرجوعی و ضمانت'],
        'newsletter' => ['icon' => 'ri-mail-send-line', 'group' => 'content', 'width' => 12, 'description' => 'عضویت در خبرنامه'],

        'contact' => ['icon' => 'ri-contacts-book-line', 'group' => 'contact', 'width' => 12, 'description' => 'فرم و اطلاعات تماس'],
        'contact_channels' => ['icon' => 'ri-customer-service-2-line', 'group' => 'contact', 'width' => 12, 'description' => 'راه‌های ارتباطی'],
        'map' => ['icon' => 'ri-map-pin-line', 'group' => 'contact', 'width' => 12, 'description' => 'نقشه موقعیت فروشگاه'],
    ];

    public static function meta(?string $key): array
    {
        return (self::ITEMS[$key] ?? []) + [
            'icon' => 'ri-layout-masonry-line',
            'group' => 'content',
            'width' => 12,
            'description' => 'سکشن صفحه‌ساز',
        ];
    }
}
