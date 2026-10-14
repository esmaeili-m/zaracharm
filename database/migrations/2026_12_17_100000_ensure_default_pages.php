<?php

use App\Support\Pages\DefaultPages;
use Illuminate\Database\Migrations\Migration;

/**
 * ساخت صفحات پیش‌فرضِ موجودنبودن (مثل سوالات متداول و شرایط مرجوعی) با سکشن پیش‌فرض
 * غیرمخرب: صفحه یا محتوای موجود تغییر نمی‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        DefaultPages::ensure();
    }

    public function down(): void
    {
        // صفحات ممکن است محتوای ساخته‌شده توسط مدیر داشته باشند؛ حذف نمی‌شوند
    }
};
