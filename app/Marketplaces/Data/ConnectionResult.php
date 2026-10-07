<?php

namespace App\Marketplaces\Data;

final class ConnectionResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly array $settings = [],
    ) {
    }

    /** @param  array  $settings  تنظیمات کشف‌شده از API (مثلاً شناسه غرفه) که ذخیره می‌شوند */
    public static function success(string $message, array $settings = []): self
    {
        return new self(true, $message, $settings);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
