<?php

namespace App\Services;

use SoapClient;
use Exception;

class SmsService
{
    public function send(string $mobile, string $code): mixed
    {
        try {

    ini_set('soap.wsdl_cache_enabled', 0);

    $client = new SoapClient(
        config('sms.wsdl'),
        [
            'encoding' => 'UTF-8',
        ]
    );

    $data = [
        'username' => config('sms.username'),
        'password' => config('sms.password'),
        'text'     => [$code],
        'to'       => $mobile,
        'bodyId'   => config('sms.body_id'),
    ];
    return $client
        ->SendByBaseNumber($data)
        ->SendByBaseNumberResult;

    } catch (Exception $exception) {

        // report() کل stack trace را لاگ می‌کرد که (با تنظیمات پیش‌فرض برخی سرورها) آرگومان‌ها،
        // یعنی رمز پنل پیامک و کد ورود، را هم شامل می‌شد. فقط پیام خطا ثبت می‌شود.
        logger()->error('SMS provider error', [
            'error' => mb_substr($exception->getMessage(), 0, 300),
        ]);

        return false;
    }

    }

}
