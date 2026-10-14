<?php

/*
|--------------------------------------------------------------------------
| پیامک (ملی پیامک)
|--------------------------------------------------------------------------
| اتصال SOAP به ملی پیامک (api.payamak-panel.com). نام کاربری/رمز فقط در .env نگهداری می‌شوند.
|
| wsdl        : وب‌سرویس Send (SendByBaseNumber، GetDeliveries، GetCredit)
| shared_wsdl : وب‌سرویس SharedService (فهرست/ثبت/ویرایش الگو)
| body_id     : کد الگوی کد ورود (OTP) — مستقل از ماژول مدیریت پیامک
*/

return [

    'wsdl' => env('PAYAMAK_WSDL', 'https://api.payamak-panel.com/post/send.asmx?wsdl'),
    'shared_wsdl' => env('PAYAMAK_SHARED_WSDL', 'https://api.payamak-panel.com/post/SharedService.asmx?wsdl'),

    'username' => env('PAYAMAK_USERNAME'),
    'password' => env('PAYAMAK_PASSWORD'),
    'body_id'  => env('PAYAMAK_BODY_ID'),

    // مهلت اتصال/پاسخ SOAP (ثانیه)
    'timeout' => (int) env('PAYAMAK_TIMEOUT', 20),

    /*
    | حالت ارسال ماژول مدیریت پیامک (کد ورود OTP همیشه واقعی ارسال می‌شود):
    | fake : هیچ پیامکی ارسال نمی‌شود؛ فقط شبیه‌سازی و ثبت در گزارش (پیش‌فرض و مناسب توسعه/تست)
    | live : ارسال واقعی و هزینه‌دار از طریق ملی پیامک
    */
    'sending' => env('SMS_SENDING', 'fake'),

    // پارامتر blackListId متد SharedServiceBodyAdd (اعمال لیست سیاه مخابرات روی الگو)
    'blacklist_id' => (int) env('PAYAMAK_BLACKLIST_ID', 1),

    // قیمت تقریبی هر پیامک (ریال) برای «هزینه تخمینی»؛ خالی = تخمین نمایش داده نمی‌شود
    'estimated_price' => env('SMS_ESTIMATED_PRICE') !== null && env('SMS_ESTIMATED_PRICE') !== ''
        ? (int) env('SMS_ESTIMATED_PRICE')
        : null,

    // ارسال گروهی: تعداد پیامک در هر اجرای Job (کمتر از retry_after صف) و مکث بین درخواست‌ها (میلی‌ثانیه)
    'batch_chunk' => (int) env('SMS_BATCH_CHUNK', 50),
    'throttle_ms' => (int) env('SMS_THROTTLE_MS', 150),

    // حداکثر تلاش برای هر پیامک (خطاهای موقت)
    'max_attempts' => 3,

    // حداکثر گیرنده در هر ارسال گروهی
    'max_recipients' => (int) env('SMS_MAX_RECIPIENTS', 20000),

    // پیگیری وضعیت تحویل تا چند ساعت پس از ارسال
    'delivery_window_hours' => 72,

    // مقادیر متغیرها پس از این تعداد روز از گزارش‌ها پاک می‌شوند (حفاظت داده)
    'args_retention_days' => (int) env('SMS_ARGS_RETENTION_DAYS', 90),

];
