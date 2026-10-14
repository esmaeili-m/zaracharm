<?php

namespace App\Services\Sms;

use Illuminate\Support\Str;

/**
 * حالت شبیه‌سازی (SMS_SENDING=fake): هیچ درخواست ارسالی به ملی پیامک فرستاده نمی‌شود.
 * پیامک‌ها «پذیرفته‌شده (شبیه‌سازی)» ثبت می‌شوند و وضعیت تحویل برایشان استعلام نمی‌شود.
 */
class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{mobile: string, bodyId: int, args: array}> برای تست‌ها */
    public array $sent = [];

    public function isLive(): bool
    {
        return false;
    }

    public function sendPattern(string $mobile, int $bodyId, array $args): SendResult
    {
        $this->sent[] = compact('mobile', 'bodyId', 'args');

        logger()->info('SMS (fake) simulated', ['mobile' => PhoneNumber::mask($mobile), 'body_id' => $bodyId]);

        return SendResult::accepted('FAKE-' . Str::upper(Str::random(12)));
    }

    public function deliveries(array $recIds): array
    {
        return [];
    }
}
