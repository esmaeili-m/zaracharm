<?php

namespace App\Services\Sms;

/**
 * درگاه ارسال ماژول مدیریت پیامک
 * پیاده‌سازی: MelipayamakClient (واقعی) | FakeSmsGateway (شبیه‌سازی؛ SMS_SENDING=fake)
 */
interface SmsGateway
{
    public function isLive(): bool;

    /** ارسال با الگوی تأییدشده خط خدماتی اشتراکی */
    public function sendPattern(string $mobile, int $bodyId, array $args): SendResult;

    /**
     * @param  list<string>  $recIds
     * @return array<string, int> recId => کد وضعیت تحویل
     */
    public function deliveries(array $recIds): array;
}
