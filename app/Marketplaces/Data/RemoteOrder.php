<?php

namespace App\Marketplaces\Data;

use Carbon\CarbonInterface;

/**
 * سفارش مارکت‌پلیس به شکل یکسان برای هسته (مبالغ به تومان، وضعیت نرمال‌شده)
 *
 * status: new | processing | shipped | delivered | problem | cancelled | returned
 */
final class RemoteOrder
{
    /** @param  RemoteOrderItem[]  $items */
    public function __construct(
        public readonly string $externalId,
        public readonly string $status,
        public readonly ?string $externalStatus,
        public readonly array $items,
        public readonly ?string $externalOrderId = null,
        public readonly string $paymentStatus = 'paid',
        public readonly ?string $customerName = null,
        public readonly ?string $customerMobile = null,
        public readonly ?string $province = null,
        public readonly ?string $city = null,
        public readonly ?string $address = null,
        public readonly ?string $postalCode = null,
        public readonly int $itemsAmount = 0,
        public readonly int $shippingAmount = 0,
        public readonly ?int $totalAmount = null,
        public readonly ?string $trackingCode = null,
        public readonly ?CarbonInterface $orderedAt = null,
        public readonly ?CarbonInterface $paidAt = null,
        public readonly array $payload = [],
    ) {
    }
}
