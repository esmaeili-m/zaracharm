<?php

namespace App\Services\Campaigns;

use App\Models\CampaignUsage;
use App\Models\Order;

/**
 * ثبت استفاده از کمپین پس از پرداخت سفارش
 *
 * هنگام ساخت سفارش (cart-item) شناسه کمپین اعمال‌شده روی قیمت هر قلم در attributes آن ذخیره می‌شود؛
 * با پرداخت سفارش، برای هر کمپین یک ردیف campaign_usages ثبت می‌شود (یک استفاده = یک سفارش).
 * idempotent: پرداخت دوباره/تأیید تکراری ردیف تکراری نمی‌سازد.
 * سقف‌های «تعداد کل استفاده» و «استفاده هر مشتری» در ProductPriceService با همین ردیف‌ها بررسی می‌شوند.
 */
class CampaignUsageRecorder
{
    public function recordForOrder(Order $order): void
    {
        $invoiceId = $order->invoice?->id;
        $perCampaign = [];

        foreach ($order->items()->get(['id', 'quantity', 'attributes']) as $item) {
            $attributes = is_array($item->attributes) ? $item->attributes : (json_decode((string) $item->attributes, true) ?: []);
            $campaignId = (int) ($attributes['campaign_id'] ?? 0);

            if (! $campaignId) {
                continue;
            }

            $perCampaign[$campaignId]['quantity'] = ($perCampaign[$campaignId]['quantity'] ?? 0) + (int) $item->quantity;
            $perCampaign[$campaignId]['discount'] = ($perCampaign[$campaignId]['discount'] ?? 0)
                + (int) ($attributes['campaign_discount'] ?? 0) * (int) $item->quantity;
        }

        foreach ($perCampaign as $campaignId => $usage) {
            $exists = CampaignUsage::where('campaign_id', $campaignId)
                ->when($invoiceId, fn ($q) => $q->where('invoice_id', $invoiceId), fn ($q) => $q->whereNull('invoice_id')->where('user_id', $order->user_id)->where('created_at', '>=', $order->created_at))
                ->exists();

            if ($exists) {
                continue;
            }

            CampaignUsage::create([
                'campaign_id' => $campaignId,
                'user_id' => $order->user_id,
                'invoice_id' => $invoiceId,
                'discount_amount' => $usage['discount'],
                'quantity' => $usage['quantity'],
            ]);
        }
    }
}
