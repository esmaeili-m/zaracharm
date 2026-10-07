<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use Throwable;

/**
 * دیجی‌پی (درگاه یکپارچه UPG — پرداخت کارتی، کیف پول و اعتباری/اقساطی)
 *
 * جریان: OAuth token → tickets/business?type=11 → انتقال به redirectUrl
 *        → بازگشت به callbackUrl (POST: result, trackingCode, providerId, amount, type)
 *        → purchases/verify → (برای خریدهای اعتباری) purchases/deliver
 *
 * مبالغ به ریال ارسال می‌شوند. providerId = uuid پرداخت (یکتا، غیرقابل حدس).
 */
class DigipayProvider extends AbstractGatewayProvider
{
    protected array $callbackKeys = ['result', 'trackingCode', 'providerId', 'amount', 'type'];

    public function key(): string
    {
        return 'digipay';
    }

    public function label(): string
    {
        return 'دیجی‌پی';
    }

    public function credentialFields(): array
    {
        return [
            'client_id' => [
                'label' => 'Client ID',
                'type' => 'secret',
                'required' => true,
                'help' => 'شناسه کلاینت دریافتی از دیجی‌پی',
            ],
            'client_secret' => [
                'label' => 'Client Secret',
                'type' => 'secret',
                'required' => true,
            ],
            'username' => [
                'label' => 'نام کاربری (Username)',
                'type' => 'secret',
                'required' => true,
            ],
            'password' => [
                'label' => 'رمز عبور (Password)',
                'type' => 'secret',
                'required' => true,
            ],
        ];
    }

    public function settingFields(): array
    {
        return [
            'base_url' => [
                'label' => 'آدرس API',
                'type' => 'url',
                'required' => true,
                'default' => 'https://digipay-api.example.com/digipay/api',
                'help' => 'مقدار نمونه است؛ آدرس محیط آزمایشی/واقعی را از دیجی‌پی دریافت و جایگزین کنید.',
            ],
            'sandbox' => [
                'label' => 'حالت آزمایشی (Sandbox)',
                'type' => 'boolean',
                'default' => true,
                'help' => 'فقط برای نمایش در پنل؛ آدرس محیط آزمایشی را در «آدرس API» وارد کنید.',
            ],
            'ticket_type' => [
                'label' => 'نوع تیکت',
                'type' => 'select',
                'default' => '11',
                'options' => [
                    '11' => 'یکپارچه (UPG) — همه روش‌ها',
                    '0' => 'فقط پرداخت کارتی (IPG)',
                    '13' => 'فقط اعتباری/اقساطی (BNPL)',
                ],
            ],
            'api_version' => [
                'label' => 'نسخه API (Digipay-Version)',
                'type' => 'text',
                'default' => '2022-02-02',
            ],
            'deliver_types' => [
                'label' => 'انواع خرید نیازمند تأیید تحویل (deliver)',
                'type' => 'text',
                'default' => '5,13',
                'help' => 'کدهای type خریدهای اعتباری با کامای انگلیسی؛ خالی = غیرفعال',
            ],
        ] + $this->amountLimitFields();
    }

    protected function headers(PaymentGateway $gateway): array
    {
        return [
            'Agent' => 'WEB',
            'Digipay-Version' => (string) $gateway->setting('api_version', '2022-02-02'),
        ];
    }

    protected function accessToken(PaymentGateway $gateway): ?string
    {
        return $this->cachedToken($gateway, function () use ($gateway) {
            try {
                $response = $this->http()
                    ->asMultipart()
                    ->withBasicAuth((string) $gateway->credential('client_id'), (string) $gateway->credential('client_secret'))
                    ->post($this->baseUrl($gateway) . '/oauth/token', [
                        ['name' => 'grant_type', 'contents' => 'password'],
                        ['name' => 'username', 'contents' => (string) $gateway->credential('username')],
                        ['name' => 'password', 'contents' => (string) $gateway->credential('password')],
                    ]);
            } catch (Throwable $e) {
                report($e);

                return [null, 0];
            }

            return [$response->json('access_token'), (int) $response->json('expires_in', 3600)];
        });
    }

    public function request(Payment $payment, PaymentGateway $gateway, string $callbackUrl, array $context = []): GatewayRequest
    {
        if (! $this->isConfigured($gateway)) {
            return GatewayRequest::failure('اطلاعات اتصال دیجی‌پی تنظیم نشده است.');
        }

        $token = $this->accessToken($gateway);

        if (! $token) {
            return GatewayRequest::failure('ارتباط با دیجی‌پی برقرار نشد. لطفاً دوباره تلاش کنید.');
        }

        $mobile = $this->internationalMobile($context['mobile'] ?? null);

        $payload = array_filter([
            'amount' => $this->rial($payment->amount),
            'cellNumber' => $mobile ? '0' . substr($mobile, 2) : null,
            'providerId' => $payment->uuid,
            'callbackUrl' => $callbackUrl,
            'basketDetailsDto' => $this->basket($payment, $context['order'] ?? null),
        ], fn ($v) => $v !== null);

        try {
            $body = (array) $this->http()->withToken($token)->withHeaders($this->headers($gateway))
                ->post($this->baseUrl($gateway) . '/tickets/business?type=' . (int) $gateway->setting('ticket_type', 11), $payload)
                ->json();
        } catch (Throwable $e) {
            report($e);

            return GatewayRequest::failure('ارتباط با دیجی‌پی برقرار نشد. لطفاً دوباره تلاش کنید.');
        }

        if ((int) ($body['result']['status'] ?? -1) === 0 && ! empty($body['ticket']) && ! empty($body['redirectUrl'])) {
            return GatewayRequest::success((string) $body['ticket'], (string) $body['redirectUrl'], $body);
        }

        return GatewayRequest::failure($this->errorMessage($body, 'ایجاد تراکنش دیجی‌پی ناموفق بود.'), $body);
    }

    protected function basket(Payment $payment, ?Order $order): array
    {
        $order ??= $payment->order;

        return [
            'basketId' => (string) ($order?->order_number ?? $payment->order_id),
            'items' => collect($order?->items ?? [])->map(fn ($item) => [
                'sellerId' => '1',
                'productId' => (string) ($item->variant_id ?? $item->id),
                'productType' => 1,
                'title' => mb_substr((string) $item->product_name, 0, 100),
                'count' => max(1, (int) $item->quantity),
            ])->values()->all(),
        ];
    }

    public function verify(Payment $payment, PaymentGateway $gateway, array $callback): GatewayVerification
    {
        $providerId = (string) ($callback['providerId'] ?? '');
        $trackingCode = (string) ($callback['trackingCode'] ?? '');
        $result = strtoupper((string) ($callback['result'] ?? ''));
        $type = (int) ($callback['type'] ?? $gateway->setting('ticket_type', 11));
        $summary = ['callback' => $this->callbackSummary($callback)];

        if ($providerId === '' || ! hash_equals((string) $payment->uuid, $providerId)) {
            return GatewayVerification::failure('شناسه تراکنش بازگشتی با پرداخت مطابقت ندارد.', $summary);
        }

        if ($result !== 'SUCCESS' || $trackingCode === '') {
            return GatewayVerification::cancelled('پرداخت دیجی‌پی توسط کاربر لغو شد یا ناموفق بود.', $summary);
        }

        if (isset($callback['amount']) && (int) $callback['amount'] !== $this->rial($payment->amount)) {
            return GatewayVerification::failure('مبلغ بازگشتی با مبلغ پرداخت مغایرت دارد.', $summary);
        }

        $token = $this->accessToken($gateway);

        if (! $token) {
            return GatewayVerification::failure('تأیید پرداخت با خطای ارتباطی مواجه شد.');
        }

        try {
            $body = (array) $this->http()->withToken($token)->withHeaders($this->headers($gateway))
                ->post($this->baseUrl($gateway) . '/purchases/verify?type=' . $type, [
                    'trackingCode' => $trackingCode,
                    'providerId' => $providerId,
                ])
                ->json();
        } catch (Throwable $e) {
            report($e);

            return GatewayVerification::failure('تأیید پرداخت با خطای ارتباطی مواجه شد.');
        }

        $raw = ['verify' => $body];

        if ((int) ($body['result']['status'] ?? -1) !== 0) {
            return GatewayVerification::failure($this->errorMessage($body, 'تأیید پرداخت دیجی‌پی ناموفق بود.'), $raw);
        }

        // مبلغ تأییدشده باید دقیقاً با مبلغ دیتابیس برابر باشد
        if (isset($body['amount']) && (int) $body['amount'] !== $this->rial($payment->amount)) {
            return GatewayVerification::failure('مبلغ تأییدشده با مبلغ پرداخت مغایرت دارد.', $raw);
        }

        if (in_array($type, $this->deliverTypes($gateway), true)) {
            $raw['deliver'] = $this->deliver($payment, $gateway, $token, $trackingCode, $type);
        }

        return GatewayVerification::success(
            (string) ($body['trackingCode'] ?? $trackingCode),
            isset($body['maskedPan']) ? (string) $body['maskedPan'] : null,
            $raw
        );
    }

    protected function deliverTypes(PaymentGateway $gateway): array
    {
        return collect(explode(',', (string) $gateway->setting('deliver_types', '5,13')))
            ->map(fn ($v) => trim($v))
            ->filter(fn ($v) => $v !== '' && is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * تأیید تحویل برای خریدهای اعتباری؛ شکست آن مانع ثبت پرداخت نیست (در لاگ پاسخ درگاه ثبت می‌شود)
     */
    protected function deliver(Payment $payment, PaymentGateway $gateway, string $token, string $trackingCode, int $type): array
    {
        $order = $payment->order;

        try {
            return (array) $this->http()->withToken($token)->withHeaders($this->headers($gateway))
                ->post($this->baseUrl($gateway) . '/purchases/deliver?type=' . $type, [
                    'deliveryDate' => now()->getTimestampMs(),
                    'invoiceNumber' => (string) ($order?->order_number ?? $payment->order_id),
                    'trackingCode' => $trackingCode,
                    'products' => collect($order?->items ?? [])->pluck('product_name')->map(fn ($n) => mb_substr((string) $n, 0, 100))->values()->all(),
                ])
                ->json();
        } catch (Throwable $e) {
            report($e);

            return ['error' => 'deliver request failed'];
        }
    }

    protected function errorMessage(array $body, string $fallback): string
    {
        $message = $body['result']['message'] ?? null;

        return is_string($message) && $message !== '' ? mb_substr($message, 0, 200) : $fallback;
    }
}
