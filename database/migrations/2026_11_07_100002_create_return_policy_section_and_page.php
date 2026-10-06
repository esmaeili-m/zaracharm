<?php

use App\Support\Sections\ReturnPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ۱) ثبت سکشن «شرایط مرجوعی» در رجیستری صفحه‌ساز (بدون اجرای SectionTableSeeder که truncate می‌کند)
 * ۲) ساخت صفحه CMS «شرایط مرجوعی» (مقصد لینک فوتر) با همین سکشن و محتوای پیش‌فرض قابل ویرایش
 * فقط مواردی که وجود ندارند ساخته می‌شوند؛ داده‌ی موجود تغییر نمی‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $sectionId = DB::table('sections')->where('key', ReturnPolicy::KEY)->value('id')
            ?? DB::table('sections')->insertGetId([
                'name' => 'شرایط مرجوعی',
                'key' => ReturnPolicy::KEY,
                'component' => 'main.sections.return-policy',
                'is_livewire' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        if (DB::table('pages')->where('slug', ReturnPolicy::PAGE_SLUG)->exists()) {
            return;
        }

        $pageId = DB::table('pages')->insertGetId([
            'title' => 'شرایط مرجوعی',
            'slug' => ReturnPolicy::PAGE_SLUG,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rowId = DB::table('page_rows')->insertGetId([
            'title' => 'ردیف 1',
            'page_id' => $pageId,
            'sort' => 0,
            'gap' => 4,
            'padding_top' => 0,
            'padding_bottom' => 0,
            'container' => 'boxed',
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('row_sections')->insert([
            'page_row_id' => $rowId,
            'section_id' => $sectionId,
            'sort' => 1,
            'title' => 'شرایط مرجوعی',
            'data' => json_encode(ReturnPolicy::defaults() + ['title' => null], JSON_UNESCAPED_UNICODE),
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
    }

    public function down(): void
    {
        $page = DB::table('pages')->where('slug', ReturnPolicy::PAGE_SLUG)->first();

        if ($page) {
            $rowIds = DB::table('page_rows')->where('page_id', $page->id)->pluck('id');
            DB::table('row_sections')->whereIn('page_row_id', $rowIds)->delete();
            DB::table('page_rows')->whereIn('id', $rowIds)->delete();
            DB::table('pages')->where('id', $page->id)->delete();
        }

        DB::table('sections')->where('key', ReturnPolicy::KEY)->delete();
    }
};
