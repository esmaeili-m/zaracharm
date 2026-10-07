<?php

namespace App\Marketplaces\Data;

final class OrderPage
{
    /** @param  RemoteOrder[]  $orders */
    public function __construct(
        public readonly array $orders,
        public readonly ?string $nextCursor = null,
    ) {
    }
}
