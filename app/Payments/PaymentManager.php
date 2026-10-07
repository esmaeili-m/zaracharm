<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Payments\Contracts\GatewayProvider;
use App\Payments\Contracts\PaymentMethodDriver;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * رجیستری روش‌ها و درگاه‌های پرداخت (از config/payments.php + جدول‌های پنل)
 */
class PaymentManager
{
    /** @var array<string, PaymentMethodDriver> */
    protected array $methods = [];

    /** @var array<string, GatewayProvider> */
    protected array $providers = [];

    public function hasMethod(string $key): bool
    {
        return array_key_exists($key, (array) config('payments.methods', []));
    }

    public function method(string $key): PaymentMethodDriver
    {
        $class = config("payments.methods.{$key}");

        if (! $class || ! is_subclass_of($class, PaymentMethodDriver::class)) {
            throw new InvalidArgumentException("روش پرداخت «{$key}» تعریف نشده است.");
        }

        return $this->methods[$key] ??= app($class);
    }

    public function hasProvider(string $key): bool
    {
        return array_key_exists($key, (array) config('payments.providers', []));
    }

    public function provider(string $key): GatewayProvider
    {
        $class = config("payments.providers.{$key}");

        if (! $class || ! is_subclass_of($class, GatewayProvider::class)) {
            throw new InvalidArgumentException("درگاه «{$key}» تعریف نشده است.");
        }

        return $this->providers[$key] ??= app($class);
    }

    /** @return array<string, GatewayProvider> */
    public function providers(): array
    {
        return collect(array_keys((array) config('payments.providers', [])))
            ->mapWithKeys(fn ($key) => [$key => $this->provider($key)])
            ->all();
    }

    /**
     * روش‌های فعال به ترتیب پنل که Driver دارند
     *
     * @return Collection<int, PaymentMethod>
     */
    public function activeMethods(): Collection
    {
        return PaymentMethod::active()->ordered()->get()
            ->filter(fn (PaymentMethod $method) => $this->hasMethod($method->key))
            ->values();
    }

    /**
     * گزینه‌های قابل نمایش در Checkout
     *
     * @return Collection<int, array{key: string, title: string, description: ?string, hint: ?string, icon: string, disabled: ?string}>
     */
    public function checkoutOptions(Order $order, ?User $user): Collection
    {
        return $this->activeMethods()->map(function (PaymentMethod $method) use ($order, $user) {
            $driver = $this->method($method->key);

            return [
                'key' => $method->key,
                'title' => $method->title,
                'description' => $method->description,
                'hint' => $driver->hint($order, $user),
                'icon' => $driver->icon(),
                'disabled' => $driver->unavailableReason($order, $user),
            ];
        });
    }

    public function isMethodUsable(string $key, Order $order, ?User $user): bool
    {
        return $this->hasMethod($key)
            && PaymentMethod::active()->where('key', $key)->exists()
            && $this->method($key)->unavailableReason($order, $user) === null;
    }

    /**
     * درگاه فعالی که Provider آن ثبت شده و اطلاعات اتصالش کامل است
     */
    public function isGatewayReady(PaymentGateway $gateway): bool
    {
        return $gateway->is_active
            && $this->hasProvider($gateway->provider)
            && $this->provider($gateway->provider)->isConfigured($gateway);
    }

    /**
     * درگاه‌های آماده به ترتیب (پیش‌فرض اول)
     *
     * @return Collection<int, PaymentGateway>
     */
    public function readyGateways(): Collection
    {
        return PaymentGateway::active()->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->get()
            ->filter(fn (PaymentGateway $gateway) => $this->isGatewayReady($gateway))
            ->values();
    }

    /**
     * درگاه‌های قابل استفاده برای این سفارش (با بررسی محدودیت‌های هر Provider)
     *
     * @return Collection<int, array{gateway: PaymentGateway, disabled: ?string}>
     */
    public function gatewayOptions(Order $order): Collection
    {
        return $this->readyGateways()->map(fn (PaymentGateway $gateway) => [
            'gateway' => $gateway,
            'disabled' => $this->provider($gateway->provider)->unavailableReason($gateway, $order),
        ]);
    }

    public function usableGateway(Order $order, ?int $gatewayId = null): ?PaymentGateway
    {
        $usable = $this->gatewayOptions($order)->whereNull('disabled')->pluck('gateway');

        return $gatewayId ? $usable->firstWhere('id', $gatewayId) : $usable->first();
    }

    /**
     * درگاه برای پرداخت آنلاین: پیش‌فرض آماده، در غیر این صورت اولین درگاه آماده
     */
    public function defaultGateway(): ?PaymentGateway
    {
        return $this->readyGateways()->first();
    }
}
