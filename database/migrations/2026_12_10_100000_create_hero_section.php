<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ثبت سکشن «هیرو» در رجیستری صفحه‌ساز (بدون اجرای SectionTableSeeder که truncate می‌کند)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('sections')->where('key', 'hero')->exists()) {
            return;
        }

        DB::table('sections')->insert([
            'name' => 'هیرو (بنر اصلی با تصویر / ویدیو)',
            'key' => 'hero',
            'component' => 'main.sections.hero',
            'is_livewire' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $sectionId = DB::table('sections')->where('key', 'hero')->value('id');

        if ($sectionId && ! DB::table('row_sections')->where('section_id', $sectionId)->exists()) {
            DB::table('sections')->where('id', $sectionId)->delete();
        }
    }
};
