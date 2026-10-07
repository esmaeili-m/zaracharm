<?php

namespace App\Marketplaces\Data;

final class RemoteOrderItem
{
    public function __construct(
        public readonly ?string $externalItemId,
        public readonly ?string $externalProductId,
        public readonly ?string $externalVariantId,
        public readonly string $title,
        public readonly int $quantity,
        public readonly int $price,          // قیمت واحد (تومان)
        public readonly ?string $sku = null,
    ) {
    }
}
