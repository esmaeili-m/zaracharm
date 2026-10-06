<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * نقش‌ها و دسترسی‌ها
 *
 * - غیرمخرب: چیزی truncate نمی‌شود و اجرای مجدد امن است (idempotent)
 * - فهرست دسترسی‌ها از App\Support\Permissions خوانده می‌شود
 * - نقش admin همه دسترسی‌ها را می‌گیرد، نقش user (مشتری) هیچ دسترسی پنل ندارد
 * - نقش‌های سفارشی که از پنل ساخته شده‌اند دست نمی‌خورند
 *
 * همین همگام‌سازی از دکمه «همگام‌سازی دسترسی‌ها» در صفحه نقش‌ها هم قابل اجراست.
 */
class RoleTableSeeder extends Seeder
{
    public function run(): void
    {
        $result = Permissions::sync();

        $this->command?->info("دسترسی‌های جدید: {$result['created']} | منسوخ حذف‌شده: {$result['removed']}");
    }
}
