<?php

namespace App\Payments\Gateways;

final class GatewayVerification
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $referenceId = null,
        public readonly ?string $cardPan = null,
        public readonly ?string $error = null,
        public readonly bool $cancelled = false,
        public readonly array $raw = [],
    ) {
    }

    public static function success(string $referenceId, ?string $cardPan = null, array $raw = []): self
    {
        return new self(true, $referenceId, $cardPan, null, false, $raw);
    }

    public static function failure(string $error, array $raw = []): self
    {
        return new self(false, null, null, $error, false, $raw);
    }

    // کاربر در صفحه درگاه انصراف داد
    public static function cancelled(string $error = 'پرداخت توسط کاربر لغو شد.', array $raw = []): self
    {
        return new self(false, null, null, $error, true, $raw);
    }
}
