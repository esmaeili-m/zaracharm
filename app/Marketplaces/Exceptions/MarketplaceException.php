<?php

namespace App\Marketplaces\Exceptions;

use RuntimeException;

/**
 * خطای ارتباط/پاسخ مارکت‌پلیس
 * retryable: خطای شبکه، 408، 429 و 5xx — صف دوباره تلاش می‌کند؛ بقیه خطاها (۴xx اعتبارسنجی) تکرار نمی‌شوند.
 */
class MarketplaceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $httpStatus = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
