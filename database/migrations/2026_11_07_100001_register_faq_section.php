<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ثبت سکشن «سوالات متداول» در رجیستری صفحه‌ساز (جدول sections) بدون اجرای SectionTableSeeder
 * (seeder جدول را truncate می‌کند). فقط در صورت نبودن، ردیف اضافه می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('sections')->where('key', 'faq')->exists()) {
            return;
        }

        DB::table('sections')->insert([
            'name' => 'سوالات متداول',
            'key' => 'faq',
            'component' => 'main.sections.faq',
            'is_livewire' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('sections')->where('key', 'faq')->delete();
    }
};
