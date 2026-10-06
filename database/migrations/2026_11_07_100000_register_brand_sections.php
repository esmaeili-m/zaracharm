<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ثبت سکشن‌های برند در رجیستری صفحه‌ساز (جدول sections) بدون اجرای SectionTableSeeder
 * (seeder جدول را truncate می‌کند). فقط ردیف‌های جدید اضافه می‌شوند.
 */
return new class extends Migration
{
    private array $sections = [
        [
            'name' => 'برندها',
            'key' => 'brands',
            'component' => 'main.sections.brands',
            'is_livewire' => 1,
        ],
        [
            'name' => 'محصولات با فیلتر برند',
            'key' => 'brandProducts',
            'component' => 'main.sections.brand-products',
            'is_livewire' => 1,
        ],
    ];

    public function up(): void
    {
        foreach ($this->sections as $section) {
            if (DB::table('sections')->where('key', $section['key'])->exists()) {
                continue;
            }

            DB::table('sections')->insert($section + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sections')
            ->whereIn('key', array_column($this->sections, 'key'))
            ->delete();
    }
};
