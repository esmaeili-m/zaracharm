<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * اسنپ‌پی (خرید اقساطی — Snapp Pay Online Merchant API v1)
 *
 * جریان: OAuth token → (eligible) → payment/v1/token → انتقال به paymentPageUrl
 *        → بازگشت به returnURL (transactionId, state) → payment/v1/verify → payment/v1/settle
 *
 * آدرس API و اطلاعات اتصال پس از عقد قرارداد توسط اسنپ‌پی ارائه می‌شود.
 * مبالغ به ریال ارسال می‌شوند. transactionId = uuid پرداخت (یکتا، غیرقابل حدس).
 */
class SnappayProvider extends AbstractGatewayProvider
{
    protected array $callbackKeys = ['transactionId', 'state', 'amount'];

    public function key(): string
    {
        return 'snappay';
    }

    public function label(): string
    {
        return 'اسنپ‌پی (اقساطی)';
    }

    public function credentialFields(): array
    {
        return [
            'client_id' => [
                'label' => 'Client ID',
                'type' => 'secret',
                'required' => true,
                'help' => 'شناسه کلاینت دریافتی از اسنپ‌پی',
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
                'default' => 'https://snappay-api.example.com',
                'help' => 'مقدار نمونه است؛ آدرس محیط آزمایشی/واقعی را از اسنپ‌پی دریافت و جایگزین کنید.',
            ],
            'sandbox' => [
                'label' => 'حالت آزمایشی (Sandbox)',
                'type' => 'boolean',
                'default' => true,
                'help' => 'فقط برای نمایش در پنل؛ آدرس محیط آزمایشی را در «آدرس API» وارد کنید.',
            ],
            'check_eligibility' => [
                'label' => 'بررسی واجد شرایط بودن مبلغ پیش از نمایش',
                'type' => 'boolean',
                'default' => true,
            ],
            'product_category' => [
                'label' => 'دسته‌بندی کالا در سبد اسنپ‌پی',
                'type' => 'text',
                'default' => 'fashion',
                'help' => 'مطابق دسته‌بندی توافق‌شده در قرارداد',
            ],
            'commission_type' => [
                'label' => 'نوع کمیسیون (commissionType)',
                'type' => 'number',
                'default' => 100,
            ],
            'description' => [
                'label' => 'توضیح تراکنش',
                'type' => 'text',
                'default' => 'پرداخت سفارش :order',
                'help' => ':order با شماره سفارش جایگزین می‌شود',
            ],
        ] + $this->amountLimitFields();
    }

    public function unavailableReason(PaymentGateway $gateway, Order $order): ?string
    {
        if ($reason = parent::unavailableReason($gateway, $order)) {
            return $reason;
        }

        if (! $gateway->setting('check_eligibility', true)) {
            return null;
        }

        $amount = $this->rial((int) $order->total_amount);

        // پاسخ موفق API برای هر مبلغ کوتاه‌مدت کش می‌شود تا صفحه پرداخت هر بار API را صدا نزند
        $cacheKey = 'snappay-eligible:' . $gateway->id . ':' . $amount;
        $eligible = Cache::get($cacheKey);

        if ($eligible === null) {
            $token = $this->accessToken($gateway);

            if (! $token) {
                return 'اسنپ‌پی در حال حاضر در دسترس نیست.';
            }

            try {
                $body = (array) $this->http()->withToken($token)
                    ->get($this->baseUrl($gateway) . '/api/online/offer/v1/eligible', ['amount' => $amount])
                    ->json();
            } catch (Throwable $e) {
                report($e);

                return 'اسنپ‌پی در حال حاضر در دسترس نیست.';
            }

            $eligible = [
                'ok' => (bool) ($body['successful'] ?? false) && (bool) ($body['response']['eligible'] ?? false),
                'message' => $body['response']['title_message'] ?? null,
            ];

            if (array_key_exists('successful', $body)) {
                Cache::put($cacheKey, $eligible, 600);
            }
        }

        return $eligible['ok'] ? null : ($eligible['message'] ?: 'خرید اقساطی اسنپ‌پی برای این مبلغ در دسترس نیست.');
    }

    protected function accessToken(PaymentGateway $gateway): ?string
    {
        return $this->cachedToken($gateway, function () use ($gateway) {
            try {
                $response = $this->http()
                    ->asForm()
                    ->withBasicAuth((string) $gateway->credential('client_id'), (string) $gateway->credential('client_secret'))
                    ->post($this->baseUrl($gateway) . '/api/online/v1/oauth/token', [
                        'grant_type' => 'password',
                        'scope' => 'online-merchant',
                        'username' => (string) $gateway->credential('username'),
                        'password' => (string) $gateway->credential('password'),
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
            return GatewayRequest::failure('اطلاعات اتصال اسنپ‌پی تنظیم نشده است.');
        }

        $mobile = $this->internationalMobile($context['mobile'] ?? null);

        if (! $mobile) {
            return GatewayRequest::failure('برای پرداخت با اسنپ‌پی شماره موبایل لازم است.');
        }

        $token = $this->accessToken($gateway);

        if (! $token) {
            return GatewayRequest::failure('ارتباط با اسنپ‌پی برقرار نشد. لطفاً دوباره تلاش کنید.');
        }

        $payload = [
            'amount' => $this->rial($payment->amount),
            'mobile' => '+' . $mobile,
            'paymentMethodTypeDto' => 'INSTALLMENT',
            'returnURL' => $callbackUrl,
            'transactionId' => $payment->uuid,
            'discountAmount' => 0,
            'externalSourceAmount' => 0,
            'cartList' => [$this->cart($payment, $gateway, $context['order'] ?? null)],
        ];
        $payload['discountAmount'] = max(0, $payload['cartList'][0]['totalAmount'] - $payload['amount']);

        try {
            $body = (array) $this->http()->withToken($token)
                ->post($this->baseUrl($gateway) . '/api/online/payment/v1/token', $payload)
                ->json();
        } catch (Throwable $e) {
            report($e);

            return GatewayRequest::failure('ارتباط با اسنپ‌پی برقرار نشد. لطفاً دوباره تلاش کنید.');
        }

        $data = (array) ($body['response'] ?? []);

        if (($body['successful'] ?? false) && ! empty($data['paymentToken']) && ! empty($data['paymentPageUrl'])) {
            return GatewayRequest::success((string) $data['paymentToken'], (string) $data['paymentPageUrl'], $body);
        }

        return GatewayRequest::failure($this->errorMessage($body, 'ایجاد تراکنش اسنپ‌پی ناموفق بود.'), $body);
    }

    /**
     * سبد خرید طبق قالب اسنپ‌پی؛ جمع اقلام + ارسال + مالیات = totalAmount و اختلاف با مبلغ پرداخت = تخفیف
     */
    protected function cart(Payment $payment, PaymentGateway $gateway, ?Order $order): array
    {
        $order ??= $payment->order;
        $items = [];

        foreach ($order?->items ?? [] as $item) {
            $items[] = [
                'id' => $item->id,
                'name' => mb_substr((string) $item->product_name, 0, 100),
                'amount' => $this->rial((int) $item->price),
                'count' => max(1, (int) $item->quantity),
                'category' => (string) $gateway->setting('product_category', 'fashion'),
                'commissionType' => (int) $gateway->setting('commission_type', 100),
            ];
        }

        if (! $items) {
            $items[] = [
                'id' => $payment->id,
                'name' => 'سفارش ' . ($order?->order_number ?? $payment->order_id),
                'amount' => $this->rial($payment->amount),
                'count' => 1,
                'category' => (string) $gateway->setting('product_category', 'fashion'),
                'commissionType' => (int) $gateway->setting('commission_type', 100),
            ];
        }

        $shipping = $this->rial((int) ($order?->shipping_amount ?? 0));
        $tax = $this->rial((int) ($order?->tax_amount ?? 0));
        $itemsTotal = array_sum(array_map(fn ($i) => $i['amount'] * $i['count'], $items));

        return [
            'cartId' => (int) ($order?->id ?? $payment->order_id),
            'cartItems' => $items,
            'isShipmentIncluded' => $shipping > 0,
            'shippingAmount' => $shipping,
            'isTaxIncluded' => $tax > 0,
            'taxAmount' => $tax,
            'totalAmount' => max($itemsTotal + $shipping + $tax, $this->rial($payment->amount)),
        ];
    }

    public function verify(Payment $payment, PaymentGateway $gateway, array $callback): GatewayVerification
    {
        $transactionId = (string) ($callback['transactionId'] ?? '');
        $state = strtoupper((string) ($callback['state'] ?? ''));

        if ($transactionId === '' || ! hash_equals((string) $payment->uuid, $transactionId) || blank($payment->authority)) {
            return GatewayVerification::failure('شناسه تراکنش بازگشتی با پرداخت مطابقت ندارد.', ['callback' => $this->callbackSummary($callback)]);
        }

        if ($state !== 'OK') {
            return GatewayVerification::cancelled('پرداخت اسنپ‌پی توسط کاربر لغو شد یا ناموفق بود.', ['callback' => $this->callbackSummary($callback)]);
        }

        if (isset($callback['amount']) && (int) $callback['amount'] !== $this->rial($payment->amount)) {
            return GatewayVerification::failure('مبلغ بازگشتی با مبلغ پرداخت مغایرت دارد.', ['callback' => $this->callbackSummary($callback)]);
        }

        $token = $this->accessToken($gateway);

        if (! $token) {
            return GatewayVerification::failure('تأیید پرداخت با خطای ارتباطی مواجه شد.');
        }

        $paymentToken = (string) $payment->authority;
        $raw = [];

        // verify؛ در صورت خطا (مثلاً درخواست تکراری) وضعیت واقعی استعلام می‌شود
        $verify = $this->call($gateway, $token, 'verify', $paymentToken);
        $raw['verify'] = $verify;

        if (! ($verify['successful'] ?? false)) {
            $status = $this->status($gateway, $token, $paymentToken);
            $raw['status'] = $status;
            $state = strtoupper((string) ($status['response']['status'] ?? ''));

            if ($state === 'SETTLE') {
                return GatewayVerification::success($transactionId, null, $raw);
            }

            if ($state !== 'VERIFY') {
                return GatewayVerification::failure($this->errorMessage($verify, 'تأیید پرداخت اسنپ‌پی ناموفق بود.'), $raw);
            }
        }

        // settle؛ در صورت شکست، تراکنش برگشت داده می‌شود تا وجه بلوکه نماند
        $settle = $this->call($gateway, $token, 'settle', $paymentToken);
        $raw['settle'] = $settle;

        if (! ($settle['successful'] ?? false)) {
            $status = $this->status($gateway, $token, $paymentToken);
            $raw['status'] = $status;

            if (strtoupper((string) ($status['response']['status'] ?? '')) === 'SETTLE') {
                return GatewayVerification::success($transactionId, null, $raw);
            }

            $raw['revert'] = $this->call($gateway, $token, 'revert', $paymentToken);

            return GatewayVerification::failure($this->errorMessage($settle, 'نهایی‌سازی پرداخت اسنپ‌پی ناموفق بود.'), $raw);
        }

        return GatewayVerification::success((string) ($settle['response']['transactionId'] ?? $transactionId), null, $raw);
    }

    protected function call(PaymentGateway $gateway, string $token, string $action, string $paymentToken): array
    {
        try {
            return (array) $this->http()->withToken($token)
                ->post($this->baseUrl($gateway) . '/api/online/payment/v1/' . $action, ['paymentToken' => $paymentToken])
                ->json();
        } catch (Throwable $e) {
            report($e);

            return ['successful' => false, 'errorData' => ['message' => 'ارتباط با اسنپ‌پی برقرار نشد.']];
        }
    }

    protected function status(PaymentGateway $gateway, string $token, string $paymentToken): array
    {
        try {
            return (array) $this->http()->withToken($token)
                ->get($this->baseUrl($gateway) . '/api/online/payment/v1/status', ['paymentToken' => $paymentToken])
                ->json();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    protected function errorMessage(array $body, string $fallback): string
    {
        $message = $body['errorData']['message'] ?? null;

        return is_string($message) && $message !== '' ? mb_substr($message, 0, 200) : $fallback;
    }
}
