<?php

namespace App\Support\Pages;

use App\Support\Sections\ReturnPolicy;
use Illuminate\Support\Facades\DB;

/**
 * صفحات پیش‌فرض سایت (منبع واحد برای seeder، migration و صفحه مدیریت صفحات)
 *
 * این صفحات قابل حذف و تغییر آدرس نیستند (لینک‌های منو/فوتر و روت‌ها به آن‌ها وابسته‌اند)؛
 * محتوای آن‌ها در صفحه‌ساز قابل ویرایش است.
 */
class DefaultPages
{
    /** slug => عنوان */
    public const PAGES = [
        'home' => 'صفحه اصلی',
        'categories' => 'دسته بندی ها',
        'special-offers' => 'شگفت انگیزها',
        'about-us' => 'درباره ما',
        'contact-us' => 'ارتباط با ما',
        'articles' => 'مقالات ما',
        'products' => 'محصولات',
        'brands' => 'برندها',
        'faq' => 'سوالات متداول',
        ReturnPolicy::PAGE_SLUG => 'شرایط مرجوعی',
    ];

    /** صفحاتی که هنگام ساخت (اگر ردیفی نداشته باشند) یک سکشن پیش‌فرض می‌گیرند: slug => کلید سکشن */
    public const CONTENT = [
        'faq' => 'faq',
        ReturnPolicy::PAGE_SLUG => ReturnPolicy::KEY,
    ];

    public static function isProtected(?string $slug): bool
    {
        return $slug !== null && array_key_exists($slug, self::PAGES);
    }

    /**
     * ساخت صفحات پیش‌فرضِ موجودنبودن + سکشن پیش‌فرض صفحات خالی (غیرمخرب و idempotent)
     *
     * @return array{pages: int, sections: int}
     */
    public static function ensure(): array
    {
        $now = now();
        $stats = ['pages' => 0, 'sections' => 0];

        foreach (self::PAGES as $slug => $title) {
            $pageId = DB::table('pages')->where('slug', $slug)->value('id');

            if (! $pageId) {
                $pageId = DB::table('pages')->insertGetId([
                    'title' => $title, 'slug' => $slug, 'status' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $stats['pages']++;
            }

            $sectionKey = self::CONTENT[$slug] ?? null;
            $sectionId = $sectionKey ? DB::table('sections')->where('key', $sectionKey)->value('id') : null;

            // فقط صفحه‌ای که هنوز هیچ ردیفی ندارد (محتوای ساخته‌شده توسط مدیر دست نمی‌خورد)
            if (! $sectionId || DB::table('page_rows')->where('page_id', $pageId)->exists()) {
                continue;
            }

            $rowId = DB::table('page_rows')->insertGetId([
                'title' => 'ردیف 1', 'page_id' => $pageId, 'sort' => 0, 'gap' => 4,
                'padding_top' => 0, 'padding_bottom' => 0, 'container' => 'boxed', 'status' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('row_sections')->insert([
                'page_row_id' => $rowId,
                'section_id' => $sectionId,
                'sort' => 1,
                'title' => $title,
                'data' => json_encode(self::sectionDefaults($sectionKey) + ['title' => null], JSON_UNESCAPED_UNICODE),
                'layout' => json_encode([
                    'grid' => ['default' => 12, 'md' => 12, 'lg' => 12],
                    'spacing' => ['padding_top' => 8, 'padding_bottom' => 8],
                    'container' => 'boxed',
                    'visibility' => ['mobile' => true, 'desktop' => true],
                ]),
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $stats['sections']++;
        }

        return $stats;
    }

    protected static function sectionDefaults(string $key): array
    {
        return match ($key) {
            ReturnPolicy::KEY => ReturnPolicy::defaults(),
            'faq' => [
                'mode' => 'latest', 'limit' => 20, 'columns' => 1, 'description' => null, 'category_id' => null,
                'faq_ids' => [], 'open_first' => true, 'show_search' => true, 'schema' => true,
            ],
            default => [],
        };
    }
}
