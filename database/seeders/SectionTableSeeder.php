<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

/**
 * رجیستری سکشن‌های صفحه‌ساز
 * غیرمخرب: بر اساس key ساخته یا به‌روزرسانی می‌شوند (truncate باعث از دست رفتن اتصال سکشن‌های صفحات می‌شد).
 */
class SectionTableSeeder extends Seeder
{
    public const SECTIONS = [
        ['name' => 'اسلایدر', 'key' => 'sliders', 'component' => 'main.sections.sliders', 'is_livewire' => 0],
        ['name' => 'دسته بندی ها', 'key' => 'categories', 'component' => 'main.sections.categories', 'is_livewire' => 1],
        ['name' => 'پیشنهادهای لحظه‌ای', 'key' => 'instantOffers', 'component' => 'main.sections.instant-offers', 'is_livewire' => 1],
        ['name' => 'محصولات', 'key' => 'products', 'component' => 'main.sections.products', 'is_livewire' => 1],
        ['name' => 'کمپین', 'key' => 'campaigns', 'component' => 'main.sections.campaigns', 'is_livewire' => 1],
        ['name' => 'خبرنامه', 'key' => 'newsletter', 'component' => 'main.sections.newsletter', 'is_livewire' => 1],
        ['name' => 'راه‌های ارتباطی', 'key' => 'contact_channels', 'component' => 'main.sections.contact-channels', 'is_livewire' => 1],
        ['name' => 'مقالات', 'key' => 'articles', 'component' => 'main.sections.articles', 'is_livewire' => 1],
        ['name' => 'درباره ما', 'key' => 'about', 'component' => 'main.sections.about', 'is_livewire' => 1],
        ['name' => 'ارتباط با ما', 'key' => 'contact', 'component' => 'main.sections.contact', 'is_livewire' => 1],
        ['name' => 'نقشه', 'key' => 'map', 'component' => 'main.sections.map', 'is_livewire' => 1],
        ['name' => 'استوری', 'key' => 'stories', 'component' => 'main.sections.stories', 'is_livewire' => 1],
        ['name' => 'برندها', 'key' => 'brands', 'component' => 'main.sections.brands', 'is_livewire' => 1],
        ['name' => 'محصولات با فیلتر برند', 'key' => 'brandProducts', 'component' => 'main.sections.brand-products', 'is_livewire' => 1],
        ['name' => 'سوالات متداول', 'key' => 'faq', 'component' => 'main.sections.faq', 'is_livewire' => 1],
        ['name' => 'شرایط مرجوعی', 'key' => 'returnPolicy', 'component' => 'main.sections.return-policy', 'is_livewire' => 1],
        ['name' => 'هیرو (بنر اصلی با تصویر / ویدیو)', 'key' => 'hero', 'component' => 'main.sections.hero', 'is_livewire' => 1],
        ['name' => 'بنرهای تبلیغاتی (تک، چندتایی یا اسلایدی)', 'key' => 'banners', 'component' => 'main.sections.banners', 'is_livewire' => 1],
        ['name' => 'اطلاعیه‌های سایت', 'key' => 'announcements', 'component' => 'main.sections.announcements', 'is_livewire' => 1],
    ];

    public function run(): void
    {
        foreach (self::SECTIONS as $section) {
            Section::updateOrCreate(['key' => $section['key']], $section);
        }
    }
}
