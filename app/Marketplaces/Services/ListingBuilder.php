<?php

namespace App\Marketplaces\Services;

use App\Marketplaces\Capability;
use App\Marketplaces\Data\ListingData;
use App\Models\Marketplace;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Throwable;

/**
 * ساخت اطلاعات قابل ارسال یک واریانت به مارکت‌پلیس از کاتالوگ فروشگاه
 *
 * قیمت: priceData() (تخفیف/کمپین) + افزایش درصدی و گرد کردن تنظیمات مارکت‌پلیس
 * موجودی: availableStock() (موجودی منهای رزرو سفارش‌های سایت) منهای ذخیره اطمینان
 */
class ListingBuilder
{
    public function build(Marketplace $marketplace, ProductVariant $variant): ListingData
    {
        $variant->loadMissing(['product.media', 'product.primaryCategory', 'product.variants', 'product.specifications', 'optionValues.optionValue.option', 'inventoryItems.inventory']);
        $product = $variant->product;

        [$price, $compare] = $this->prices($marketplace, $variant);

        $active = (bool) $product?->status && (bool) $variant->status && ! $variant->trashed();
        $stock = $this->stock($marketplace, $variant);

        // مارکت‌پلیسی که وضعیت فعال/غیرفعال ندارد: محصول غیرفعال = موجودی صفر
        if (! $active && ! $marketplace->supports(Capability::UPDATE_STATUS)) {
            $stock = 0;
        }

        $variantTitle = $variant->optionValues
            ->map(fn ($ov) => $ov->optionValue?->title)
            ->filter()
            ->implode(' / ');

        return new ListingData(
            productId: (int) $variant->product_id,
            variantId: (int) $variant->id,
            sku: $variant->sku ?: null,
            title: trim((string) $product?->title),
            variantTitle: ($product?->variants->count() ?? 0) > 1 && $variantTitle !== '' ? $variantTitle : null,
            description: $this->plain((string) ($product?->description ?: $product?->short_description)),
            brief: $product?->short_description ? $this->plain($product->short_description) : null,
            images: $this->images($variant),
            price: $price,
            comparePrice: $compare,
            stock: $stock,
            active: $active,
            weight: $variant->weight ? (int) $variant->weight : null,
            url: $product ? route('products.show', $product->slug) : url('/'),
            categoryName: $product?->primaryCategory?->title,
            specs: $this->specs($variant),
        );
    }

    /** @return array{0: int, 1: ?int} */
    protected function prices(Marketplace $marketplace, ProductVariant $variant): array
    {
        $base = (int) $variant->price;
        $final = $base;
        $before = (int) $variant->compare_price ?: null;

        if ($marketplace->setting('use_final_price', true)) {
            try {
                $data = $variant->priceData();

                if (! empty($data)) {
                    $final = (int) ($data['after_discount'] ?? $base);
                    $before = ! empty($data['has_discount']) ? (int) ($data['before_discount'] ?? $base) : $before;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        $markup = (float) $marketplace->setting('price_markup_percent', 0);
        $rounding = (int) $marketplace->setting('price_rounding', 0);

        $apply = function (?int $amount) use ($markup, $rounding): ?int {
            if (! $amount) {
                return $amount;
            }

            $amount = (int) round($amount * (1 + $markup / 100));

            return $rounding > 0 ? (int) (ceil($amount / $rounding) * $rounding) : $amount;
        };

        $price = (int) $apply($final);
        $compare = $apply($before);

        return [$price, $compare && $compare > $price ? $compare : null];
    }

    public function stock(Marketplace $marketplace, ProductVariant $variant): int
    {
        $stock = $variant->availableStock() - max(0, (int) $marketplace->setting('stock_buffer', 0));
        $max = (int) $marketplace->setting('max_stock', 0);

        $stock = max(0, $stock);

        return $max > 0 ? min($stock, $max) : $stock;
    }

    /** @return array<int, array{url: string, path: ?string, disk: string}> */
    protected function images(ProductVariant $variant): array
    {
        $media = $variant->product?->media ?? collect();

        return $media
            ->filter(fn ($m) => in_array($m->collection, ['featured_image', 'gallery'], true))
            ->sortBy(fn ($m) => [$m->collection === 'featured_image' ? 0 : 1, (int) $m->sort])
            ->map(fn ($m) => [
                'url' => $m->external_url ?: url('/storage/' . $m->file_path),
                'path' => $m->external_url ? null : $m->file_path,
                'disk' => $m->disk ?: 'public',
            ])
            ->filter(fn ($img) => filled($img['url']))
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    protected function specs(ProductVariant $variant): array
    {
        $specs = [];

        foreach ($variant->product?->specifications ?? [] as $spec) {
            $pivot = $spec->pivot;

            if (isset($pivot->status) && ! $pivot->status) {
                continue;
            }

            $value = $pivot->text_value ?? $pivot->number_value ?? $pivot->decimal_value ?? $pivot->date_value
                ?? ($pivot->boolean_value !== null ? ($pivot->boolean_value ? 'بله' : 'خیر') : null);

            if ($value !== null && $value !== '') {
                $specs[(string) $spec->title] = (string) $value;
            }
        }

        foreach ($variant->optionValues as $ov) {
            $option = $ov->optionValue?->option?->title ?? null;
            if ($option && $ov->optionValue?->title) {
                $specs[$option] = $ov->optionValue->title;
            }
        }

        return $specs;
    }

    protected function plain(string $html): string
    {
        $text = preg_replace(['/<br\s*\/?>/i', '/<\/(p|div|li|h[1-6])>/i'], "\n", $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", Str::of($text)->replaceMatches('/[ \t]+/', ' ')->toString()));
    }
}
