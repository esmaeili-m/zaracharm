<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * اطلاعیه‌های سایت: لینک اختیاری + ترتیب، و ثبت سکشن «اطلاعیه‌ها» در صفحه‌ساز
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'link')) {
                $table->string('link', 500)->nullable()->after('type');
                $table->string('link_text', 60)->nullable()->after('link');
            }
            if (! Schema::hasColumn('announcements', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('is_active');
            }
        });

        if (! DB::table('sections')->where('key', 'announcements')->exists()) {
            DB::table('sections')->insert([
                'name' => 'اطلاعیه‌های سایت',
                'key' => 'announcements',
                'component' => 'main.sections.announcements',
                'is_livewire' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $sectionId = DB::table('sections')->where('key', 'announcements')->value('id');

        if ($sectionId && ! DB::table('row_sections')->where('section_id', $sectionId)->exists()) {
            DB::table('sections')->where('id', $sectionId)->delete();
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['link', 'link_text', 'is_pinned']);
        });
    }
};
