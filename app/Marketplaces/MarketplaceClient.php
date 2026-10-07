<?php

namespace App\Marketplaces;

use App\Marketplaces\Exceptions\MarketplaceException;
use App\Models\Marketplace;
use App\Models\MarketplaceSyncLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * کلاینت HTTP مشترک Adapterها
 *
 * هر درخواست/پاسخ/خطا در marketplace_sync_logs ثبت می‌شود (اطلاعات محرمانه حذف می‌شود)
 * و خطاها به MarketplaceException با علامت retryable تبدیل می‌شوند.
 */
class MarketplaceClient
{
    // کلیدهایی که مقدارشان در لاگ نمایش داده نمی‌شود
    protected const SECRET_KEYS = '/pass|secret|token|authorization|api[_-]?key|client_id|refresh/i';

    protected array $context = [];

    public function __construct(public readonly Marketplace $marketplace)
    {
    }

    /**
     * @param  array{listing_id?: ?int, order_id?: ?int, attempt?: int}  $context
     */
    public function withContext(array $context): static
    {
        $clone = clone $this;
        $clone->context = array_merge($this->context, $context);

        return $clone;
    }

    /**
     * ارسال درخواست
     *
     * @param  array{json?: array, query?: array, form?: array, multipart?: array, headers?: array, token?: string, basic?: array, timeout?: int, log_request?: bool}  $options
     */
    public function send(string $operation, string $method, string $url, array $options = []): Response
    {
        $request = Http::timeout((int) ($options['timeout'] ?? config('marketplaces.http_timeout', 30)))
            ->acceptJson()
            ->withHeaders((array) ($options['headers'] ?? []));

        if (! empty($options['token'])) {
            $request = $request->withToken($options['token']);
        }

        if (! empty($options['basic'])) {
            $request = $request->withBasicAuth(...$options['basic']);
        }

        $payload = $this->preparePayload($request, $options);
        $started = microtime(true);

        try {
            $response = $request->send(strtoupper($method), $url, $payload);
        } catch (ConnectionException $e) {
            $this->record($operation, 'failed', $method, $url, $options, null, null, 'خطای ارتباط: ' . $e->getMessage(), $started, true);

            throw new MarketplaceException('ارتباط با ' . $this->marketplace->title . ' برقرار نشد.', true);
        } catch (Throwable $e) {
            $this->record($operation, 'failed', $method, $url, $options, null, null, $e->getMessage(), $started, false);

            throw new MarketplaceException('خطای ارسال درخواست به ' . $this->marketplace->title . ': ' . $e->getMessage());
        }

        $ok = $response->successful();
        $retryable = ! $ok && ($response->status() === 408 || $response->status() === 429 || $response->serverError());

        $this->record(
            $operation,
            $ok ? 'success' : 'failed',
            $method,
            $url,
            $options,
            $response->status(),
            $this->responseBody($response),
            $ok ? null : $this->errorMessage($response),
            $started,
            $retryable
        );

        return $response;
    }

    /**
     * ارسال + تبدیل پاسخ ناموفق به Exception
     */
    public function sendOrFail(string $operation, string $method, string $url, array $options = []): Response
    {
        $response = $this->send($operation, $method, $url, $options);

        if ($response->failed()) {
            throw $this->exceptionFor($response);
        }

        return $response;
    }

    public function exceptionFor(Response $response): MarketplaceException
    {
        $status = $response->status();

        return new MarketplaceException(
            $this->marketplace->title . ' (HTTP ' . $status . '): ' . $this->errorMessage($response),
            $status === 408 || $status === 429 || $response->serverError(),
            $status,
            $status === 429 ? ((int) $response->header('Retry-After') ?: null) : null
        );
    }

    /** ثبت رویداد غیر HTTP (وب‌هوک، ثبت سفارش، ...) */
    public function log(string $operation, string $status, string $message, array $data = [], string $direction = 'out'): MarketplaceSyncLog
    {
        return MarketplaceSyncLog::create([
            'marketplace_id' => $this->marketplace->id,
            'marketplace_listing_id' => $this->context['listing_id'] ?? null,
            'marketplace_order_id' => $this->context['order_id'] ?? null,
            'operation' => $operation,
            'direction' => $direction,
            'status' => $status,
            'request' => $data ? $this->truncate($this->redact($data)) : null,
            'message' => mb_substr($message, 0, 500),
            'attempt' => (int) ($this->context['attempt'] ?? 1),
        ]);
    }

    protected function preparePayload(PendingRequest &$request, array $options): array
    {
        $payload = [];

        if (! empty($options['query'])) {
            $payload['query'] = $options['query'];
        }

        if (array_key_exists('json', $options)) {
            $request = $request->asJson();
            $payload['json'] = $options['json'];
        } elseif (array_key_exists('form', $options)) {
            $request = $request->asForm();
            $payload['form_params'] = $options['form'];
        } elseif (array_key_exists('multipart', $options)) {
            $request = $request->asMultipart();
            $payload['multipart'] = $options['multipart'];
        }

        return $payload;
    }

    protected function record(string $operation, string $status, string $method, string $url, array $options, ?int $httpStatus, ?array $response, ?string $message, float $started, bool $retryable): void
    {
        try {
            $requestLog = array_filter([
                'query' => $options['query'] ?? null,
                'body' => $options['json'] ?? $options['form'] ?? null,
                'multipart' => isset($options['multipart'])
                    ? array_map(fn ($part) => ['name' => $part['name'] ?? null, 'filename' => $part['filename'] ?? null], $options['multipart'])
                    : null,
            ], fn ($v) => $v !== null);

            if (($options['log_request'] ?? true) === false) {
                $requestLog = ['body' => '[hidden]'];
            }

            MarketplaceSyncLog::create([
                'marketplace_id' => $this->marketplace->id,
                'marketplace_listing_id' => $this->context['listing_id'] ?? null,
                'marketplace_order_id' => $this->context['order_id'] ?? null,
                'operation' => $operation,
                'direction' => 'out',
                'status' => $status,
                'method' => strtoupper($method),
                'url' => mb_substr($this->redactUrl($url), 0, 500),
                'http_status' => $httpStatus,
                'request' => $requestLog ? $this->truncate($this->redact($requestLog)) : null,
                'response' => $response ? $this->truncate($this->redact($response)) : null,
                'message' => $message ? mb_substr($message, 0, 500) : null,
                'attempt' => (int) ($this->context['attempt'] ?? 1),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'retryable' => $retryable,
            ]);
        } catch (Throwable $e) {
            report($e); // خطای ثبت لاگ نباید عملیات اصلی را متوقف کند
        }
    }

    protected function responseBody(Response $response): ?array
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        $body = trim((string) $response->body());

        return $body === '' ? null : ['raw' => mb_substr(strip_tags($body), 0, 2000)];
    }

    public function errorMessage(Response $response): string
    {
        $json = (array) $response->json();

        foreach (['message', 'error_description', 'error.message', 'errors.0.message', 'detail', 'messages.0', 'errorData.message', 'result.message', 'error'] as $key) {
            $value = data_get($json, $key);

            if (is_string($value) && trim($value) !== '') {
                return mb_substr(trim($value), 0, 300);
            }
        }

        return match (true) {
            $response->status() === 401 => 'اطلاعات احراز هویت نامعتبر یا منقضی است.',
            $response->status() === 403 => 'دسترسی لازم برای این عملیات وجود ندارد.',
            $response->status() === 404 => 'آدرس یا رکورد موردنظر یافت نشد.',
            $response->status() === 422 => 'اطلاعات ارسالی معتبر نیست.',
            $response->status() === 429 => 'تعداد درخواست‌ها بیش از حد مجاز است.',
            $response->serverError() => 'خطای سرور مارکت‌پلیس.',
            default => 'پاسخ ناموفق.',
        };
    }

    protected function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEYS, $key) && ! is_array($value)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    protected function redactUrl(string $url): string
    {
        return preg_replace('/((?:token|secret|key|password)=)[^&]+/i', '$1***', $url);
    }

    protected function truncate(array $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $limit = (int) config('marketplaces.log_body_limit', 20000);

        return strlen((string) $json) > $limit
            ? ['truncated' => true, 'preview' => mb_substr((string) $json, 0, $limit)]
            : $data;
    }
}
