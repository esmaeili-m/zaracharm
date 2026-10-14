<?php

namespace App\Services\Sms;

use RuntimeException;

/**
 * خطای ارتباط با ملی پیامک (پیام بدون نام کاربری/رمز)
 * reached=false یعنی درخواست قطعاً به سرویس نرسیده است (مثلاً عدم اتصال)
 */
class SmsProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $reached = true)
    {
        parent::__construct($message);
    }
}
