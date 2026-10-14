<?php

namespace App\Services\Sms;

/**
 * نتیجه یک درخواست ارسال
 * status: accepted (recId دریافت شد) | failed (خطای قطعی سرویس یا عدم اتصال) | unknown (نتیجه نامعلوم)
 */
final class SendResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $recId = null,
        public readonly ?int $code = null,
        public readonly ?string $message = null,
        public readonly bool $permanent = false,
        public readonly bool $stopBatch = false,
    ) {
    }

    public static function accepted(string $recId): self
    {
        return new self('accepted', recId: $recId);
    }

    public static function failed(?int $code, string $message, bool $permanent = false, bool $stopBatch = false): self
    {
        return new self('failed', code: $code, message: $message, permanent: $permanent, stopBatch: $stopBatch);
    }

    public static function unknown(string $message, ?int $code = null): self
    {
        return new self('unknown', code: $code, message: $message);
    }
}
