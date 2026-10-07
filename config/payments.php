<?php

/*
|--------------------------------------------------------------------------
| سیستم پرداخت
|--------------------------------------------------------------------------
|
| methods   : روش‌های پرداخت (key => کلاس Driver)؛ فعال/غیرفعال و ترتیب از پنل (جدول payment_methods)
| providers : ارائه‌دهندگان درگاه بانکی (key => کلاس Provider)؛ درگاه‌ها و Credentials از پنل
|
| افزودن روش/درگاه جدید = یک کلاس + یک خط در این فایل (هسته سیستم تغییر نمی‌کند)
|
*/

return [

    'methods' => [
        'gateway' => App\Payments\Methods\GatewayMethod::class,
        'wallet' => App\Payments\Methods\WalletMethod::class,
        'transfer' => App\Payments\Methods\CardToCardMethod::class,
        'cod' => App\Payments\Methods\CashOnDeliveryMethod::class,
    ],

    'providers' => [
        'zarinpal' => App\Payments\Gateways\ZarinpalProvider::class,
        'snappay' => App\Payments\Gateways\SnappayProvider::class,
        'digipay' => App\Payments\Gateways\DigipayProvider::class,
    ],

    // مهلت پرداخت دوباره بعد از رد شدن کارت‌به‌کارت (ساعت)
    'retry_window_hours' => 24,

    // مهلت HTTP درخواست به درگاه (ثانیه)
    'http_timeout' => 20,

];
