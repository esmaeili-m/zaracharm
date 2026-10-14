<?php

namespace Database\Seeders;

use App\Support\Pages\DefaultPages;
use Illuminate\Database\Seeder;

/**
 * صفحات پیش‌فرض (فهرست در App\Support\Pages\DefaultPages)
 * غیرمخرب: فقط صفحات موجودنبودن ساخته می‌شوند؛ صفحات سوالات متداول و شرایط مرجوعی سکشن پیش‌فرض می‌گیرند.
 * SectionTableSeeder باید قبل از این اجرا شود.
 */
class PageTableSeeder extends Seeder
{
    public function run(): void
    {
        $stats = DefaultPages::ensure();

        $this->command?->info("صفحات جدید: {$stats['pages']} ، سکشن‌های پیش‌فرض: {$stats['sections']}");
    }
}
