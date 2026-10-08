<?php

namespace App\Services\Catalog;

use App\Models\ProductVariant;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * تولید SKU و بارکد یکتا برای تنوع‌های محصول
 *
 * SKU   : P{شناسه محصول}-{۶ کاراکتر تصادفی} مثل P125-7KD9QX (بدون حروف/اعداد مبهم O/0/I/1)
 * بارکد : EAN-13 با پیشوند 200 (بازه کدهای داخلی فروشگاه، با کد کشورها تداخل ندارد) + رقم کنترل
 *
 * یکتایی با بررسی دیتابیس (شامل رکوردهای حذف‌نرم‌شده، چون ایندکس یکتای sku آن‌ها را هم شامل می‌شود)
 * و مقادیر رزروشده در همین درخواست تضمین می‌شود. تغییر دستی بعدی مشکلی ایجاد نمی‌کند، چون
 * قیمت، موجودی، سفارش و اتصال‌ها همه با شناسه تنوع (id) کار می‌کنند، نه SKU/بارکد.
 */
class VariantCodeGenerator
{
    protected const SKU_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** مقادیر تولیدشده در همین درخواست (قبل از ذخیره، برای ساخت گروهی) */
    protected array $reservedSkus = [];

    protected array $reservedBarcodes = [];

    public function sku(?int $productId = null): string
    {
        $prefix = 'P' . ($productId ?: '0') . '-';

        for ($i = 0; $i < 20; $i++) {
            $random = '';
            for ($j = 0; $j < 6; $j++) {
                $random .= self::SKU_ALPHABET[random_int(0, strlen(self::SKU_ALPHABET) - 1)];
            }

            $sku = $prefix . $random;

            if (! isset($this->reservedSkus[$sku]) && ! $this->skuExists($sku)) {
                return $this->reservedSkus[$sku] = $sku;
            }
        }

        // احتمال بسیار کم؛ با بخش طولانی‌تر
        $sku = $prefix . strtoupper(Str::random(12));

        if ($this->skuExists($sku)) {
            throw new RuntimeException('تولید SKU یکتا ممکن نشد.');
        }

        return $this->reservedSkus[$sku] = $sku;
    }

    public function barcode(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $body = '200' . str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
            $barcode = $body . $this->ean13CheckDigit($body);

            if (! isset($this->reservedBarcodes[$barcode]) && ! $this->barcodeExists($barcode)) {
                return $this->reservedBarcodes[$barcode] = $barcode;
            }
        }

        throw new RuntimeException('تولید بارکد یکتا ممکن نشد.');
    }

    public function skuExists(string $sku, ?int $ignoreId = null): bool
    {
        return ProductVariant::withTrashed()
            ->where('sku', $sku)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function barcodeExists(string $barcode, ?int $ignoreId = null): bool
    {
        return ProductVariant::query()
            ->where('barcode', $barcode)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    /** رقم کنترل EAN-13 برای ۱۲ رقم اول */
    public function ean13CheckDigit(string $twelveDigits): int
    {
        $sum = 0;
        foreach (str_split($twelveDigits) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
