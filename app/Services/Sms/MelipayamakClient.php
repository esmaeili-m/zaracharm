<?php

namespace App\Services\Sms;

use SoapClient;
use SoapFault;
use Throwable;

/**
 * اتصال SOAP به ملی پیامک (api.payamak-panel.com)
 *
 * متدهای استفاده‌شده (مطابق WSDL رسمی):
 *  Send.asmx          : SendByBaseNumber(username, password, text[], to, bodyId) → recId یا کد خطا
 *                       GetDeliveries(recIds[], username, password) → int[]
 *                       GetCredit(username, password) → double
 *  SharedService.asmx : GetSharedServiceBody(username, password) → ShareServiceBody[] {BodyID, Title, Body, InsertDate, BodyStatus, Description}
 *                       SharedServiceBodyAdd(username, password, title, body, blackListId) → int
 *                       SharedServiceBodyEdit(username, password, bodyId, body) → int
 *
 * نام کاربری/رمز هرگز در پیام خطا یا لاگ قرار نمی‌گیرد.
 */
class MelipayamakClient implements SmsGateway
{
    /** @var array<string, SoapClient> */
    protected array $clients = [];

    public function isLive(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return filled(config('sms.username')) && filled(config('sms.password')) && filled(config('sms.wsdl'));
    }

    public function sendPattern(string $mobile, int $bodyId, array $args): SendResult
    {
        try {
            $raw = $this->call('wsdl', 'SendByBaseNumber', [
                'text' => ['string' => array_map('strval', array_values($args))],
                'to' => $mobile,
                'bodyId' => $bodyId,
            ]);
        } catch (SmsProviderException $e) {
            // اگر درخواست به سرویس نرسیده، ارسال قطعاً انجام نشده و قابل تکرار است؛ وگرنه نامشخص
            return $e->reached
                ? SendResult::unknown($e->getMessage())
                : SendResult::failed(null, $e->getMessage());
        }

        return MelipayamakCodes::sendResult((string) ($raw->SendByBaseNumberResult ?? ''));
    }

    /**
     * ارسال کد ورود (سازگار با SmsService قدیمی؛ خروجی خام سرویس)
     */
    public function sendOtp(string $mobile, string $code, int $bodyId): string
    {
        return (string) ($this->call('wsdl', 'SendByBaseNumber', [
            'text' => ['string' => [$code]],
            'to' => $mobile,
            'bodyId' => $bodyId,
        ])->SendByBaseNumberResult ?? '');
    }

    public function deliveries(array $recIds): array
    {
        $recIds = array_values(array_filter($recIds, fn ($id) => MelipayamakCodes::isRecId((string) $id)));

        if (! $recIds) {
            return [];
        }

        $result = $this->call('wsdl', 'GetDeliveries', [
            'recIds' => ['long' => array_map('strval', $recIds)],
        ]);

        $codes = $this->asList($result->GetDeliveriesResult->int ?? []);
        $map = [];

        foreach ($recIds as $i => $recId) {
            if (array_key_exists($i, $codes)) {
                $map[(string) $recId] = (int) $codes[$i];
            }
        }

        return $map;
    }

    /** اعتبار پنل (مقدار گزارش‌شده توسط ملی پیامک) */
    public function credit(): float
    {
        return (float) ($this->call('wsdl', 'GetCredit', [])->GetCreditResult ?? 0);
    }

    /**
     * @return list<array{id: int, title: string, body: string, status: int, created: ?string, description: ?string}>
     */
    public function bodies(): array
    {
        $result = $this->call('shared_wsdl', 'GetSharedServiceBody', []);
        $items = $this->asList($result->GetSharedServiceBodyResult->ShareServiceBody ?? []);

        return array_values(array_map(fn ($item) => [
            'id' => (int) $item->BodyID,
            'title' => (string) ($item->Title ?? ''),
            'body' => (string) ($item->Body ?? ''),
            'status' => (int) ($item->BodyStatus ?? 0),
            'created' => isset($item->InsertDate) ? (string) $item->InsertDate : null,
            'description' => isset($item->Description) && $item->Description !== '' ? (string) $item->Description : null,
        ], array_filter($items)));
    }

    /** ثبت الگوی جدید؛ خروجی خام سرویس */
    public function addBody(string $title, string $body): int
    {
        return (int) ($this->call('shared_wsdl', 'SharedServiceBodyAdd', [
            'title' => $title,
            'body' => $body,
            'blackListId' => (int) config('sms.blacklist_id', 1),
        ])->SharedServiceBodyAddResult ?? 0);
    }

    /** ویرایش متن الگو؛ خروجی خام سرویس */
    public function editBody(int $bodyId, string $body): int
    {
        return (int) ($this->call('shared_wsdl', 'SharedServiceBodyEdit', [
            'bodyId' => $bodyId,
            'body' => $body,
        ])->SharedServiceBodyEditResult ?? 0);
    }

    protected function call(string $wsdlKey, string $method, array $params): object
    {
        if (! $this->isConfigured()) {
            throw new SmsProviderException('اطلاعات اتصال ملی پیامک (PAYAMAK_USERNAME / PAYAMAK_PASSWORD) تنظیم نشده است.', false);
        }

        $params = ['username' => config('sms.username'), 'password' => config('sms.password')] + $params;

        $previousTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', (string) config('sms.timeout', 20));

        try {
            return $this->client($wsdlKey)->__soapCall($method, [$params]);
        } catch (SoapFault $e) {
            throw new SmsProviderException($this->describe($e), ! $this->notReached($e));
        } catch (Throwable $e) {
            throw new SmsProviderException('خطای ارتباط با ملی پیامک: ' . mb_substr($e->getMessage(), 0, 200));
        } finally {
            ini_set('default_socket_timeout', (string) $previousTimeout);
        }
    }

    protected function client(string $wsdlKey): SoapClient
    {
        return $this->clients[$wsdlKey] ??= new SoapClient(config('sms.' . $wsdlKey), [
            'encoding' => 'UTF-8',
            'exceptions' => true,
            'connection_timeout' => (int) config('sms.timeout', 20),
            'cache_wsdl' => WSDL_CACHE_BOTH,
        ]);
    }

    /** خطاهایی که یعنی درخواست اصلاً به سرویس نرسیده است */
    protected function notReached(SoapFault $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'could not connect')
            || str_contains($message, 'parsing wsdl')
            || str_contains($message, "couldn't load")
            || str_contains($message, 'failed to load external entity');
    }

    protected function describe(SoapFault $e): string
    {
        $message = mb_substr($e->getMessage(), 0, 200);

        // اطمینان از نبود اطلاعات محرمانه در پیام
        foreach ([config('sms.username'), config('sms.password')] as $secret) {
            if (filled($secret)) {
                $message = str_replace((string) $secret, '***', $message);
            }
        }

        return 'خطای ارتباط با ملی پیامک: ' . $message;
    }

    protected function asList(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        return is_array($value) ? array_values($value) : [$value];
    }
}
