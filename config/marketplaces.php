<?php

/*
|--------------------------------------------------------------------------
| اتصال به مارکت‌پلیس‌ها
|--------------------------------------------------------------------------
|
| providers : key => کلاس Adapter (پیاده‌سازی App\Marketplaces\Contracts\MarketplaceProvider)
|             اطلاعات اتصال، تنظیمات و فعال/غیرفعال بودن از پنل (جدول marketplaces) مدیریت می‌شود.
|
| افزودن مارکت‌پلیس جدید = یک کلاس Adapter + یک خط در این فایل + یک ردیف در جدول marketplaces
| (ردیف از صفحه «مارکت‌پلیس‌ها» در پنل هم ساخته می‌شود). هسته همگام‌سازی تغییر نمی‌کند.
|
*/

return [

    'providers' => [
        'digikala' => App\Marketplaces\Providers\DigikalaProvider::class,
        'basalam' => App\Marketplaces\Providers\BasalamProvider::class,
        'torob' => App\Marketplaces\Providers\TorobProvider::class,
    ],

    // مهلت HTTP درخواست به مارکت‌پلیس (ثانیه)
    'http_timeout' => 30,

    // تلاش مجدد صف برای درخواست‌های ناموفق قابل تکرار (خطای شبکه، 429، 5xx)
    'retry' => [
        'tries' => 5,
        'backoff' => [60, 300, 900, 1800],  // ثانیه
    ],

    // تأخیر همگام‌سازی خودکار موجودی پس از تغییر انبار (تجمیع تغییرات پشت‌سرهم)
    'stock_sync_delay' => 15,

    // حداکثر صفحه‌های دریافت سفارش در هر اجرا
    'order_pull_max_pages' => 5,

    // نگهداری لاگ‌ها (روز)
    'log_retention_days' => 60,

    // حداکثر اندازه بدنه درخواست/پاسخ ذخیره‌شده در لاگ (بایت)
    'log_body_limit' => 20000,

];
