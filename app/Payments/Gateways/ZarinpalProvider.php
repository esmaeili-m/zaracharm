<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Models\PaymentGateway;
use Throwable;

/**
 * درگاه زرین‌پال (REST API نسخه ۴)
 * https://www.zarinpal.com/docs/paymentGateway/
 *
 * مبالغ پروژه به تومان است؛ برای یکدستی درخواست و تأیید، مبلغ به ریال (×۱۰) ارسال می‌شود.
 */
class ZarinpalProvider extends AbstractGatewayProvider
{
    protected array $callbackKeys = ['Authority', 'Status'];

    public function key(): string
    {
        return 'zarinpal';
    }

    public function label(): string
    {
        return 'زرین‌پال';
    }

    public function credentialFields(): array
    {
        return [
            'merchant_id' => [
                'label' => 'مرچنت کد (Merchant ID)',
                'type' => 'secret',
                'required' => true,
                'help' => 'کد ۳۶ کاراکتری درگاه از پنل زرین‌پال',
            ],
        ];
    }

    public function settingFields(): array
    {
        return [
            'sandbox' => [
                'label' => 'حالت آزمایشی (Sandbox)',
                'type' => 'boolean',
                'default' => false,
                'help' => 'برای تست بدون پرداخت واقعی',
            ],
            'description' => [
                'label' => 'توضیح تراکنش',
                'type' => 'text',
                'default' => 'پرداخت سفارش :order',
                'help' => ':order با شماره سفارش جایگزین می‌شود',
            ],
        ] + $this->amountLimitFields();
    }

    protected function baseUrl(PaymentGateway $gateway): string
    {
        return $gateway->setting('sandbox')
            ? 'https://sandbox.zarinpal.com/pg'
            : 'https://payment.zarinpal.com/pg';
    }

    public function request(Payment $payment, PaymentGateway $gateway, string $callbackUrl, array $context = []): GatewayRequest
    {
        $merchant = (string) $gateway->credential('merchant_id');

        if ($merchant === '') {
            return GatewayRequest::failure('مرچنت کد درگاه تنظیم نشده است.');
        }

        $description = $this->description($gateway, $context, $payment->order_id);

        $payload = array_filter([
            'merchant_id' => $merchant,
            'amount' => $this->rial($payment->amount),
            'callback_url' => $callbackUrl,
            'description' => mb_substr($description, 0, 500),
            'metadata' => array_filter([
                'mobile' => $context['mobile'] ?? null,
                'email' => $context['email'] ?? null,
                'order_id' => (string) ($context['order_number'] ?? $payment->order_id),
            ]),
        ]);

        try {
            $response = $this->http()
                ->post($this->baseUrl($gateway) . '/v4/payment/request.json', $payload);
        } catch (Throwable $e) {
            report($e);

            return GatewayRequest::failure('ارتباط با درگاه زرین‌پال برقرار نشد. لطفاً دوباره تلاش کنید.');
        }

        $body = (array) $response->json();
        $data = (array) ($body['data'] ?? []);

        if ((int) ($data['code'] ?? 0) === 100 && ! empty($data['authority'])) {
            return GatewayRequest::success(
                (string) $data['authority'],
                $this->baseUrl($gateway) . '/StartPay/' . $data['authority'],
                $this->safeRaw($body)
            );
        }

        return GatewayRequest::failure($this->errorMessage($body), $this->safeRaw($body));
    }

    public function verify(Payment $payment, PaymentGateway $gateway, array $callback): GatewayVerification
    {
        $authority = (string) ($callback['Authority'] ?? '');
        $status = strtoupper((string) ($callback['Status'] ?? ''));

        // Authority باید دقیقاً همان باشد که هنگام ایجاد تراکنش ذخیره شده
        if ($authority === '' || ! hash_equals((string) $payment->authority, $authority)) {
            return GatewayVerification::failure('شناسه تراکنش بازگشتی با پرداخت مطابقت ندارد.', ['callback' => $callback]);
        }

        if ($status !== 'OK') {
            return GatewayVerification::cancelled('پرداخت توسط کاربر لغو شد یا ناموفق بود.', ['callback' => $callback]);
        }

        try {
            $response = $this->http()
                ->post($this->baseUrl($gateway) . '/v4/payment/verify.json', [
                    'merchant_id' => (string) $gateway->credential('merchant_id'),
                    // مبلغ از دیتابیس خوانده می‌شود، نه از درخواست کاربر
                    'amount' => $this->rial($payment->amount),
                    'authority' => $authority,
                ]);
        } catch (Throwable $e) {
            report($e);

            return GatewayVerification::failure('تأیید پرداخت با خطای ارتباطی مواجه شد.');
        }

        $body = (array) $response->json();
        $data = (array) ($body['data'] ?? []);
        $code = (int) ($data['code'] ?? 0);

        // 100 = موفق ، 101 = قبلاً تأیید شده (درخواست تکراری)
        if (in_array($code, [100, 101], true) && ! empty($data['ref_id'])) {
            return GatewayVerification::success((string) $data['ref_id'], $data['card_pan'] ?? null, $this->safeRaw($body));
        }

        return GatewayVerification::failure($this->errorMessage($body), $this->safeRaw($body));
    }

    protected function errorMessage(array $body): string
    {
        $code = (int) ($body['errors']['code'] ?? $body['data']['code'] ?? 0);

        return match ($code) {
            -9 => 'اطلاعات ارسالی به درگاه نامعتبر است.',
            -10 => 'آی‌پی یا مرچنت کد پذیرنده صحیح نیست.',
            -11 => 'مرچنت کد فعال نیست.',
            -12 => 'تلاش بیش از حد در یک بازه زمانی کوتاه.',
            -15 => 'درگاه پرداخت به حالت تعلیق درآمده است.',
            -50 => 'مبلغ پرداخت‌شده با مبلغ تراکنش مغایرت دارد.',
            -51 => 'پرداخت ناموفق بود.',
            -54 => 'شناسه تراکنش نامعتبر است.',
            -55 => 'تراکنش یافت نشد.',
            default => (string) ($body['errors']['message'] ?? 'خطا در پرداخت (کد ' . $code . ').'),
        };
    }

    // ذخیره پاسخ درگاه بدون اطلاعات حساس
    protected function safeRaw(array $body): array
    {
        unset($body['data']['card_hash']);

        return $body;
    }
}
