<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ثبت سکشن «بنرها» در رجیستری صفحه‌ساز (بدون اجرای SectionTableSeeder که truncate می‌کند)
 * تصاویر: media با collection = banner_desktop / banner_mobile و purpose = uid آیتم
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('sections')->where('key', 'banners')->exists()) {
            return;
        }

        DB::table('sections')->insert([
            'name' => 'بنرهای تبلیغاتی (تک، چندتایی یا اسلایدی)',
            'key' => 'banners',
            'component' => 'main.sections.banners',
            'is_livewire' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $sectionId = DB::table('sections')->where('key', 'banners')->value('id');

        if ($sectionId && ! DB::table('row_sections')->where('section_id', $sectionId)->exists()) {
            DB::table('sections')->where('id', $sectionId)->delete();
        }
    }
};
