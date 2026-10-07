<?php

namespace App\Marketplaces\Exceptions;

/**
 * عملیاتی که API رسمی این مارکت‌پلیس پشتیبانی نمی‌کند
 */
class UnsupportedOperationException extends MarketplaceException
{
    public function __construct(string $message = 'این عملیات توسط API این مارکت‌پلیس پشتیبانی نمی‌شود.')
    {
        parent::__construct($message, false);
    }
}
