<?php

namespace App\Marketplaces\Data;

/**
 * محصول/تنوع موجود در مارکت‌پلیس (مبالغ به تومان)
 */
final class RemoteListing
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $externalVariantId,
        public readonly string $title,
        public readonly ?string $sku = null,
        public readonly ?int $price = null,
        public readonly ?int $stock = null,
        public readonly ?bool $active = null,
        public readonly ?string $url = null,
    ) {
    }
}
