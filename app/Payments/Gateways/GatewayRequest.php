<?php

namespace App\Payments\Gateways;

final class GatewayRequest
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $authority = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {
    }

    public static function success(string $authority, string $redirectUrl, array $raw = []): self
    {
        return new self(true, $authority, $redirectUrl, null, $raw);
    }

    public static function failure(string $error, array $raw = []): self
    {
        return new self(false, null, null, $error, $raw);
    }
}
