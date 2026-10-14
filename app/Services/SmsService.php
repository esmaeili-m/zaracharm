<?php

namespace App\Services;

use App\Services\Sms\MelipayamakClient;
use Exception;

/**
 * ارسال کد ورود (OTP) با الگوی PAYAMAK_BODY_ID
 * همیشه واقعی ارسال می‌شود (مستقل از SMS_SENDING ماژول مدیریت پیامک)
 */
class SmsService
{
    public function send(string $mobile, string $code): mixed
    {
        try {
            return app(MelipayamakClient::class)->sendOtp($mobile, $code, (int) config('sms.body_id'));
        } catch (Exception $exception) {
            // پیام خطای کلاینت بدون نام کاربری/رمز است؛ stack trace (که آرگومان‌ها را دارد) لاگ نمی‌شود
            logger()->error('SMS provider error', [
                'error' => mb_substr($exception->getMessage(), 0, 300),
            ]);

            return false;
        }
    }
}
