<?php

namespace App\Marketplaces\Data;

/**
 * اطلاعات قابل ارسال یک واریانت به مارکت‌پلیس (مبالغ به تومان؛ تبدیل واحد با Adapter است)
 */
final class ListingData
{
    /**
     * @param  array<int, array{url: string, path: ?string, disk: string}>  $images
     * @param  array<string, string>  $specs
     */
    public function __construct(
        public readonly int $productId,
        public readonly int $variantId,
        public readonly ?string $sku,
        public readonly string $title,
        public readonly ?string $variantTitle,
        public readonly string $description,
        public readonly ?string $brief,
        public readonly array $images,
        public readonly int $price,
        public readonly ?int $comparePrice,
        public readonly int $stock,
        public readonly bool $active,
        public readonly ?int $weight,
        public readonly string $url,
        public readonly ?string $categoryName,
        public readonly array $specs = [],
    ) {
    }

    /** اثر انگشت محتوا (نام/توضیحات/تصاویر) برای جلوگیری از ارسال تکراری */
    public function contentHash(): string
    {
        return sha1(json_encode([$this->title, $this->description, $this->brief, array_column($this->images, 'url')]));
    }

    public function imagesHash(): string
    {
        return sha1(json_encode(array_column($this->images, 'url')));
    }
}
