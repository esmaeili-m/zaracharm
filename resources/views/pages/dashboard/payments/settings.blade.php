<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\BankCard;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new class extends Component
{
    #[Url(except: 'methods')]
    public string $tab = 'methods';     // methods | gateways | cards

    // ---- روش ----
    public ?int $methodId = null;
    public string $methodTitle = '';
    public ?string $methodDescription = null;
    public $methodMaxAmount = null;     // فقط پرداخت در محل

    // ---- درگاه ----
    public ?int $gatewayId = null;
    public string $provider = '';
    public string $gatewayTitle = '';
    public array $credentials = [];     // ورودی جدید (خالی = بدون تغییر)
    public array $gatewaySettings = [];
    public bool $gatewayActive = false;
    public bool $gatewayDefault = false;

    // ---- کارت ----
    public ?int $cardId = null;
    public ?string $cardTitle = null;
    public string $bankName = '';
    public string $ownerName = '';
    public string $cardNumber = '';
    public ?string $sheba = null;
    public bool $cardActive = true;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('payment-settings.view'), 403);

        if (!in_array($this->tab, ['methods', 'gateways', 'cards'], true)) {
            $this->tab = 'methods';
        }
    }

    protected function authorizeEdit(): void
    {
        abort_if(!auth()->user()->can('payment-settings.edit'), 403);
    }

    protected function manager(): PaymentManager
    {
        return app(PaymentManager::class);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['methods', 'gateways', 'cards'], true) ? $tab : 'methods';
        $this->resetErrorBag();
    }

    /*
    |--------------------------------------------------------------------------
    | روش‌های پرداخت
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function methods()
    {
        return PaymentMethod::ordered()->get();
    }

    public function toggleMethod(int $id): void
    {
        $this->authorizeEdit();

        $method = PaymentMethod::findOrFail($id);

        if (!$method->is_active && !$this->manager()->hasMethod($method->key)) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'برای این روش پیاده‌سازی (Driver) وجود ندارد.');
            return;
        }

        $method->update(['is_active' => !$method->is_active]);
        unset($this->methods);
    }

    public function moveMethod(int $id, string $direction): void
    {
        $this->authorizeEdit();

        $list = PaymentMethod::ordered()->get()->values();
        $index = $list->search(fn ($m) => $m->id === $id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || !isset($list[$swap])) {
            return;
        }

        DB::transaction(function () use ($list, $index, $swap) {
            $items = $list->all();
            [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];
            foreach ($items as $i => $item) {
                $item->update(['sort' => $i + 1]);
            }
        });

        unset($this->methods);
    }

    public function editMethod(int $id): void
    {
        $method = PaymentMethod::findOrFail($id);
        $this->resetErrorBag();
        $this->methodId = $method->id;
        $this->methodTitle = $method->title;
        $this->methodDescription = $method->description;
        $this->methodMaxAmount = $method->settings['max_amount'] ?? null;
    }

    public function saveMethod(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'methodTitle' => ['required', 'string', 'max:100'],
            'methodDescription' => ['nullable', 'string', 'max:191'],
            'methodMaxAmount' => ['nullable', 'integer', 'min:0'],
        ], [
            'methodTitle.required' => 'عنوان روش پرداخت الزامی است.',
            'methodTitle.max' => 'عنوان حداکثر ۱۰۰ کاراکتر است.',
            'methodDescription.max' => 'توضیح حداکثر ۱۹۱ کاراکتر است.',
            'methodMaxAmount.integer' => 'سقف مبلغ باید عدد باشد.',
        ]);

        $method = PaymentMethod::findOrFail($this->methodId);
        $settings = (array) $method->settings;

        if ($method->key === 'cod') {
            $settings['max_amount'] = $this->methodMaxAmount ? (int) $this->methodMaxAmount : null;
        }

        $method->update([
            'title' => trim($this->methodTitle),
            'description' => filled($this->methodDescription) ? trim($this->methodDescription) : null,
            'settings' => $settings,
        ]);

        unset($this->methods);
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'روش پرداخت ذخیره شد.');
    }

    /*
    |--------------------------------------------------------------------------
    | درگاه‌ها
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function gateways()
    {
        return PaymentGateway::orderByDesc('is_default')->orderBy('sort')->orderBy('id')->withCount('payments')->get();
    }

    #[Computed]
    public function providers(): array
    {
        return $this->manager()->providers();
    }

    #[Computed]
    public function providerFields(): array
    {
        if (!$this->provider || !$this->manager()->hasProvider($this->provider)) {
            return ['credentials' => [], 'settings' => []];
        }

        $provider = $this->manager()->provider($this->provider);

        return ['credentials' => $provider->credentialFields(), 'settings' => $provider->settingFields()];
    }

    // اعتبارنامه‌هایی که برای این درگاه ذخیره شده‌اند (فقط نام فیلد، نه مقدار)
    #[Computed]
    public function storedCredentialKeys(): array
    {
        if (!$this->gatewayId) {
            return [];
        }

        $gateway = PaymentGateway::find($this->gatewayId);

        return array_keys(array_filter((array) $gateway?->credentials, fn ($v) => filled($v)));
    }

    public function updatedProvider(): void
    {
        $this->credentials = [];
        $this->gatewaySettings = collect($this->providerFields['settings'])->map(fn ($f) => $f['default'] ?? null)->all();
    }

    public function newGateway(): void
    {
        $this->resetErrorBag();
        $this->reset(['gatewayId', 'gatewayTitle', 'credentials', 'gatewaySettings', 'gatewayActive', 'gatewayDefault']);
        $this->provider = (string) array_key_first($this->providers);
        $this->updatedProvider();
    }

    public function editGateway(int $id): void
    {
        $gateway = PaymentGateway::findOrFail($id);
        $this->resetErrorBag();
        $this->gatewayId = $gateway->id;
        $this->provider = $gateway->provider;
        $this->gatewayTitle = $gateway->title;
        $this->credentials = [];   // مقدار محرمانه هرگز به فرم برگردانده نمی‌شود
        $this->gatewaySettings = array_merge(
            collect($this->providerFields['settings'])->map(fn ($f) => $f['default'] ?? null)->all(),
            (array) $gateway->settings
        );
        $this->gatewayActive = $gateway->is_active;
        $this->gatewayDefault = $gateway->is_default;
        unset($this->storedCredentialKeys);
    }

    public function saveGateway(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'provider' => ['required', Rule::in(array_keys($this->providers))],
            'gatewayTitle' => ['required', 'string', 'max:100'],
            'credentials' => ['array'],
            'credentials.*' => ['nullable', 'string', 'max:500'],
            'gatewaySettings' => ['array'],
        ], [
            'provider.required' => 'نوع درگاه را انتخاب کنید.',
            'provider.in' => 'نوع درگاه معتبر نیست.',
            'gatewayTitle.required' => 'عنوان درگاه الزامی است.',
            'gatewayTitle.max' => 'عنوان حداکثر ۱۰۰ کاراکتر است.',
            'credentials.*.max' => 'مقدار وارد شده بیش از حد طولانی است.',
        ]);

        $gateway = $this->gatewayId ? PaymentGateway::findOrFail($this->gatewayId) : new PaymentGateway(['sort' => (int) PaymentGateway::max('sort') + 1]);

        // ادغام: فیلد خالی = مقدار قبلی حفظ شود
        $stored = $gateway->provider === $this->provider ? (array) $gateway->credentials : [];
        $credentials = $stored;

        foreach ($this->providerFields['credentials'] as $key => $field) {
            $value = trim((string) ($this->credentials[$key] ?? ''));

            if ($value !== '') {
                $credentials[$key] = $value;
            }

            // اطلاعات ناقص قابل ذخیره است، ولی درگاه تا تکمیل آن فعال نمی‌شود
            if ($this->gatewayActive && ($field['required'] ?? false) && blank($credentials[$key] ?? null)) {
                $this->addError('credentials.' . $key, '«' . $field['label'] . '» برای فعال‌سازی درگاه الزامی است.');
            }
        }

        $settings = [];
        foreach ($this->providerFields['settings'] as $key => $field) {
            $value = $this->gatewaySettings[$key] ?? ($field['default'] ?? null);

            $settings[$key] = match ($field['type'] ?? 'text') {
                'boolean' => (bool) $value,
                'number' => is_numeric($value) ? max(0, (int) $value) : (int) ($field['default'] ?? 0),
                'select' => array_key_exists((string) $value, (array) ($field['options'] ?? [])) ? (string) $value : ($field['default'] ?? null),
                default => is_string($value) ? mb_substr(trim($value), 0, 255) : $value,
            };

            if (($field['type'] ?? 'text') === 'url' && filled($settings[$key]) && !filter_var($settings[$key], FILTER_VALIDATE_URL)) {
                $this->addError('gatewaySettings.' . $key, '«' . $field['label'] . '» باید یک آدرس معتبر باشد.');
            }

            if ($this->gatewayActive && ($field['required'] ?? false) && blank($settings[$key])) {
                $this->addError('gatewaySettings.' . $key, '«' . $field['label'] . '» برای فعال‌سازی درگاه الزامی است.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use ($gateway, $credentials, $settings) {
            $gateway->fill([
                'provider' => $this->provider,
                'title' => trim($this->gatewayTitle),
                'credentials' => $credentials,
                'settings' => $settings,
                'is_active' => $this->gatewayActive,
                'is_default' => $this->gatewayDefault,
            ])->save();

            if ($gateway->is_default) {
                PaymentGateway::where('id', '!=', $gateway->id)->update(['is_default' => false]);
            }
        });

        $this->credentials = [];
        unset($this->gateways, $this->storedCredentialKeys);
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'درگاه ذخیره شد.');
    }

    public function toggleGateway(int $id): void
    {
        $this->authorizeEdit();

        $gateway = PaymentGateway::findOrFail($id);

        if (!$gateway->is_active && !$this->isConfigured($gateway)) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'ابتدا اطلاعات اتصال درگاه را تکمیل کنید.');
            return;
        }

        $gateway->update(['is_active' => !$gateway->is_active]);
        unset($this->gateways);
    }

    // Provider ثبت شده و اطلاعات اتصال لازم وارد شده است
    public function isConfigured(PaymentGateway $gateway): bool
    {
        return $this->manager()->hasProvider($gateway->provider)
            && $this->manager()->provider($gateway->provider)->isConfigured($gateway);
    }

    public function makeDefault(int $id): void
    {
        $this->authorizeEdit();

        DB::transaction(function () use ($id) {
            PaymentGateway::query()->update(['is_default' => false]);
            PaymentGateway::whereKey($id)->update(['is_default' => true]);
        });

        unset($this->gateways);
    }

    public function deleteGateway(int $id): void
    {
        $this->authorizeEdit();

        $gateway = PaymentGateway::findOrFail($id);

        // پرداخت در جریان دارد => فقط غیرفعال
        if ($gateway->payments()->where('status', 'pending')->exists()) {
            $gateway->update(['is_active' => false]);
            $this->dispatch('alert', type: 'warning', title: 'غیرفعال شد', text: 'این درگاه پرداخت در جریان دارد و فقط غیرفعال شد.');
        } else {
            $gateway->update(['is_active' => false, 'is_default' => false]);
            $gateway->delete();
            $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'درگاه حذف شد.');
        }

        unset($this->gateways);
    }

    /*
    |--------------------------------------------------------------------------
    | کارت‌های بانکی
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function cards()
    {
        return BankCard::orderBy('sort')->orderBy('id')->get();
    }

    public function newCard(): void
    {
        $this->resetErrorBag();
        $this->reset(['cardId', 'cardTitle', 'bankName', 'ownerName', 'cardNumber', 'sheba']);
        $this->cardActive = true;
    }

    public function editCard(int $id): void
    {
        $card = BankCard::findOrFail($id);
        $this->resetErrorBag();
        $this->cardId = $card->id;
        $this->cardTitle = $card->title;
        $this->bankName = $card->bank_name;
        $this->ownerName = $card->owner_name;
        $this->cardNumber = $card->card_number;
        $this->sheba = $card->sheba;
        $this->cardActive = $card->is_active;
    }

    protected function digits(?string $value): string
    {
        return preg_replace('/\D/', '', strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]));
    }

    public function saveCard(): void
    {
        $this->authorizeEdit();

        $this->cardNumber = $this->digits($this->cardNumber);
        $this->sheba = filled($this->sheba) ? 'IR' . $this->digits($this->sheba) : null;

        $this->validate([
            'cardTitle' => ['nullable', 'string', 'max:100'],
            'bankName' => ['required', 'string', 'max:100'],
            'ownerName' => ['required', 'string', 'max:100'],
            'cardNumber' => ['required', 'digits:16', Rule::unique('bank_cards', 'card_number')->ignore($this->cardId)->whereNull('deleted_at')],
            'sheba' => ['nullable', 'regex:/^IR\d{24}$/'],
        ], [
            'bankName.required' => 'نام بانک الزامی است.',
            'ownerName.required' => 'نام صاحب حساب الزامی است.',
            'cardNumber.required' => 'شماره کارت الزامی است.',
            'cardNumber.digits' => 'شماره کارت باید ۱۶ رقم باشد.',
            'cardNumber.unique' => 'این شماره کارت قبلاً ثبت شده است.',
            'sheba.regex' => 'شماره شبا باید IR و ۲۴ رقم باشد.',
        ]);

        $payload = [
            'title' => filled($this->cardTitle) ? trim($this->cardTitle) : null,
            'bank_name' => trim($this->bankName),
            'owner_name' => trim($this->ownerName),
            'card_number' => $this->cardNumber,
            'sheba' => $this->sheba,
            'is_active' => $this->cardActive,
        ];

        $this->cardId
            ? BankCard::findOrFail($this->cardId)->update($payload)
            : BankCard::create($payload + ['sort' => (int) BankCard::max('sort') + 1]);

        unset($this->cards);
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'کارت بانکی ذخیره شد.');
    }

    public function toggleCard(int $id): void
    {
        $this->authorizeEdit();

        $card = BankCard::findOrFail($id);
        $card->update(['is_active' => !$card->is_active]);
        unset($this->cards);
    }

    public function deleteCard(int $id): void
    {
        $this->authorizeEdit();

        // پرداخت‌های ثبت‌شده اطلاعات کارت را در meta دارند؛ حذف نرم
        BankCard::findOrFail($id)->delete();
        unset($this->cards);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'کارت حذف شد.');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">تنظیمات پرداخت</h1>
            <div class="text-muted small">روش‌های پرداخت، درگاه‌های بانکی و کارت‌های کارت‌به‌کارت</div>
        </div>
        @can('payments.view')
            <a href="{{ route('payments.index') }}" class="btn btn-primary-light btn-wave"><i class="ri-exchange-dollar-line align-middle"></i> پرداخت‌ها</a>
        @endcan
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach(['methods' => 'روش‌های پرداخت', 'gateways' => 'درگاه‌های بانکی', 'cards' => 'کارت‌های بانکی'] as $key => $label)
            <li class="nav-item">
                <button type="button" wire:click="setTab('{{ $key }}')" class="nav-link {{ $tab === $key ? 'active' : '' }}">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    {{-- ===================== روش‌ها ===================== --}}
    @if($tab === 'methods')
        <div class="card custom-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap">
                        <thead><tr><th>ترتیب</th><th>عنوان</th><th>کلید</th><th>توضیح</th><th>وضعیت</th><th></th></tr></thead>
                        <tbody>
                        @foreach($this->methods as $method)
                            <tr wire:key="method-{{ $method->id }}">
                                <td>
                                    @can('payment-settings.edit')
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-light" wire:click="moveMethod({{ $method->id }}, 'up')" @disabled($loop->first)><i class="ri-arrow-up-line"></i></button>
                                            <button type="button" class="btn btn-light" wire:click="moveMethod({{ $method->id }}, 'down')" @disabled($loop->last)><i class="ri-arrow-down-line"></i></button>
                                        </div>
                                    @endcan
                                </td>
                                <td class="fw-semibold">{{ $method->title }}</td>
                                <td><code>{{ $method->key }}</code>
                                    @unless(app(\App\Payments\PaymentManager::class)->hasMethod($method->key))
                                        <span class="badge bg-danger-transparent">بدون Driver</span>
                                    @endunless
                                </td>
                                <td class="small text-muted text-wrap" style="max-width: 280px">{{ $method->description }}</td>
                                <td>
                                    <span role="button" wire:click="toggleMethod({{ $method->id }})" class="badge bg-outline-{{ $method->is_active ? 'success' : 'secondary' }}">
                                        {{ $method->is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                </td>
                                <td>
                                    @can('payment-settings.edit')
                                        <a href="#methodModal" data-bs-toggle="modal" wire:click="editMethod({{ $method->id }})" class="text-info"><i class="ri-edit-line"></i></a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-text">ترتیب این جدول، ترتیب نمایش روش‌ها در صفحه پرداخت است. روش‌های غیرقابل استفاده (مثلاً کیف پول با موجودی ناکافی) به‌صورت غیرفعال نمایش داده می‌شوند.</div>
            </div>
        </div>
    @endif

    {{-- ===================== درگاه‌ها ===================== --}}
    @if($tab === 'gateways')
        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div class="card-title">درگاه‌های بانکی</div>
                @can('payment-settings.edit')
                    <button type="button" class="btn btn-sm btn-success-light" data-bs-toggle="modal" href="#gatewayModal" wire:click="newGateway"><i class="ri-add-line"></i> افزودن درگاه</button>
                @endcan
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap">
                        <thead><tr><th>عنوان</th><th>ارائه‌دهنده</th><th>حالت</th><th>پرداخت‌ها</th><th>پیش‌فرض</th><th>وضعیت</th><th></th></tr></thead>
                        <tbody>
                        @forelse($this->gateways as $gateway)
                            <tr wire:key="gateway-{{ $gateway->id }}">
                                <td class="fw-semibold">{{ $gateway->title }}</td>
                                <td>
                                    {{ isset($this->providers[$gateway->provider]) ? $this->providers[$gateway->provider]->label() : $gateway->provider }}
                                    @unless($this->isConfigured($gateway))
                                        <span class="badge bg-danger-transparent ms-1" title="اطلاعات اتصال وارد نشده؛ درگاه قابل فعال‌سازی نیست">تنظیم نشده</span>
                                    @endunless
                                </td>
                                <td>
                                    @if($gateway->setting('sandbox'))
                                        <span class="badge bg-warning-transparent">آزمایشی</span>
                                    @else
                                        <span class="badge bg-success-transparent">واقعی</span>
                                    @endif
                                </td>
                                <td>{{ number_format($gateway->payments_count) }}</td>
                                <td>
                                    @if($gateway->is_default)
                                        <span class="badge bg-primary">پیش‌فرض</span>
                                    @else
                                        @can('payment-settings.edit')
                                            <button type="button" class="btn btn-sm btn-link p-0" wire:click="makeDefault({{ $gateway->id }})">انتخاب به‌عنوان پیش‌فرض</button>
                                        @endcan
                                    @endif
                                </td>
                                <td>
                                    <span role="button" wire:click="toggleGateway({{ $gateway->id }})" class="badge bg-outline-{{ $gateway->is_active ? 'success' : 'secondary' }}">
                                        {{ $gateway->is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                </td>
                                <td>
                                    @can('payment-settings.edit')
                                        <div class="hstack gap-2">
                                            <a href="#gatewayModal" data-bs-toggle="modal" wire:click="editGateway({{ $gateway->id }})" class="text-info"><i class="ri-edit-line"></i></a>
                                            <a href="javascript:void(0)" wire:click="deleteGateway({{ $gateway->id }})" wire:confirm="درگاه حذف (یا در صورت داشتن پرداخت در جریان، غیرفعال) شود؟" class="text-danger"><i class="ri-delete-bin-5-line"></i></a>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">درگاهی تعریف نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="form-text">در صفحه پرداخت از درگاه «پیش‌فرض» فعال استفاده می‌شود؛ اگر پیش‌فرض فعال نباشد، اولین درگاه فعال انتخاب می‌شود.</div>
            </div>
        </div>
    @endif

    {{-- ===================== کارت‌ها ===================== --}}
    @if($tab === 'cards')
        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div class="card-title">کارت‌های کارت‌به‌کارت</div>
                @can('payment-settings.edit')
                    <button type="button" class="btn btn-sm btn-success-light" data-bs-toggle="modal" href="#cardModal" wire:click="newCard"><i class="ri-add-line"></i> افزودن کارت</button>
                @endcan
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap">
                        <thead><tr><th>عنوان</th><th>بانک</th><th>شماره کارت</th><th>صاحب حساب</th><th>شبا</th><th>وضعیت</th><th></th></tr></thead>
                        <tbody>
                        @forelse($this->cards as $card)
                            <tr wire:key="card-{{ $card->id }}">
                                <td>{{ $card->title ?: '—' }}</td>
                                <td>{{ $card->bank_name }}</td>
                                <td dir="ltr" class="fw-semibold">{{ $card->formatted_number }}</td>
                                <td>{{ $card->owner_name }}</td>
                                <td dir="ltr" class="small">{{ $card->sheba ?: '—' }}</td>
                                <td>
                                    <span role="button" wire:click="toggleCard({{ $card->id }})" class="badge bg-outline-{{ $card->is_active ? 'success' : 'secondary' }}">
                                        {{ $card->is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                </td>
                                <td>
                                    @can('payment-settings.edit')
                                        <div class="hstack gap-2">
                                            <a href="#cardModal" data-bs-toggle="modal" wire:click="editCard({{ $card->id }})" class="text-info"><i class="ri-edit-line"></i></a>
                                            <a href="javascript:void(0)" wire:click="deleteCard({{ $card->id }})" wire:confirm="کارت حذف شود؟" class="text-danger"><i class="ri-delete-bin-5-line"></i></a>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">کارتی ثبت نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="form-text">فقط کارت‌های فعال در صفحه پرداخت به مشتری نمایش داده می‌شوند.</div>
            </div>
        </div>
    @endif

    {{-- مودال روش --}}
    <div wire:ignore.self class="modal fade" id="methodModal">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form wire:submit="saveMethod">
                <div class="modal-header"><h6 class="modal-title">ویرایش روش پرداخت</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label">عنوان</label>
                        <input type="text" wire:model="methodTitle" class="form-control @error('methodTitle') is-invalid @enderror">
                        @error('methodTitle') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">توضیح کوتاه</label>
                        <input type="text" wire:model="methodDescription" class="form-control @error('methodDescription') is-invalid @enderror">
                        @error('methodDescription') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    @if($methodId && optional($this->methods->firstWhere('id', $methodId))->key === 'cod')
                        <div class="mb-3">
                            <label class="form-label">سقف مبلغ سفارش برای پرداخت در محل (تومان)</label>
                            <input type="number" min="0" wire:model="methodMaxAmount" class="form-control @error('methodMaxAmount') is-invalid @enderror">
                            <div class="form-text">خالی یا ۰ = بدون سقف</div>
                            @error('methodMaxAmount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    @endif
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">ذخیره</button><button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button></div>
            </form>
        </div></div>
    </div>

    {{-- مودال درگاه --}}
    <div wire:ignore.self class="modal fade" id="gatewayModal">
        <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
            <form wire:submit="saveGateway" autocomplete="off">
                <div class="modal-header"><h6 class="modal-title">{{ $gatewayId ? 'ویرایش درگاه' : 'افزودن درگاه' }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-start">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ارائه‌دهنده</label>
                            <select wire:model.live="provider" class="form-select @error('provider') is-invalid @enderror" @disabled($gatewayId)>
                                @foreach($this->providers as $key => $providerDriver)
                                    <option value="{{ $key }}">{{ $providerDriver->label() }}</option>
                                @endforeach
                            </select>
                            @error('provider') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">عنوان (نمایش در صفحه پرداخت)</label>
                            <input type="text" wire:model="gatewayTitle" class="form-control @error('gatewayTitle') is-invalid @enderror" placeholder="مثلاً: زرین‌پال">
                            @error('gatewayTitle') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        @foreach($this->providerFields['credentials'] as $key => $field)
                            @php
                                $stored = in_array($key, $this->storedCredentialKeys, true);
                            @endphp
                            <div class="col-md-6" wire:key="cred-{{ $provider }}-{{ $key }}">
                                <label class="form-label">{{ $field['label'] }} @if($field['required'] ?? false)<span class="text-danger">*</span>@endif</label>
                                <input type="{{ ($field['type'] ?? 'text') === 'secret' ? 'password' : 'text' }}"
                                       wire:model="credentials.{{ $key }}"
                                       autocomplete="new-password" dir="ltr"
                                       class="form-control @error('credentials.' . $key) is-invalid @enderror"
                                       placeholder="{{ $stored ? '•••••••• ذخیره شده (برای تغییر، مقدار جدید وارد کنید)' : '' }}">
                                @if(!empty($field['help']))<div class="form-text">{{ $field['help'] }}</div>@endif
                                @error('credentials.' . $key) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        @endforeach

                        @foreach($this->providerFields['settings'] as $key => $field)
                            <div class="col-md-6" wire:key="set-{{ $provider }}-{{ $key }}">
                                @if(($field['type'] ?? 'text') === 'boolean')
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" id="gs-{{ $key }}" wire:model="gatewaySettings.{{ $key }}">
                                        <label class="form-check-label" for="gs-{{ $key }}">{{ $field['label'] }}</label>
                                    </div>
                                @elseif(($field['type'] ?? 'text') === 'select')
                                    <label class="form-label">{{ $field['label'] }}</label>
                                    <select wire:model="gatewaySettings.{{ $key }}" class="form-select">
                                        @foreach($field['options'] ?? [] as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <label class="form-label">{{ $field['label'] }} @if($field['required'] ?? false)<span class="text-danger">*</span>@endif</label>
                                    <input type="{{ ($field['type'] ?? 'text') === 'number' ? 'number' : 'text' }}" @if(in_array($field['type'] ?? 'text', ['url', 'number'], true)) dir="ltr" @endif
                                           wire:model="gatewaySettings.{{ $key }}" class="form-control @error('gatewaySettings.' . $key) is-invalid @enderror">
                                    @error('gatewaySettings.' . $key) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                @endif
                                @if(!empty($field['help']))<div class="form-text">{{ $field['help'] }}</div>@endif
                            </div>
                        @endforeach

                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="g-active" wire:model="gatewayActive">
                                <label class="form-check-label" for="g-active">فعال</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="g-default" wire:model="gatewayDefault">
                                <label class="form-check-label" for="g-default">درگاه پیش‌فرض</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        آدرس بازگشت (Callback) به‌صورت خودکار برای هر تراکنش ساخته می‌شود و نیازی به تنظیم آن نیست. اطلاعات محرمانه به‌صورت رمزنگاری‌شده ذخیره می‌شوند.
                        اطلاعات ناقص قابل ذخیره است، اما درگاه تا تکمیل فیلدهای ستاره‌دار قابل فعال‌سازی نیست.
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveGateway">ذخیره</button><button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button></div>
            </form>
        </div></div>
    </div>

    {{-- مودال کارت --}}
    <div wire:ignore.self class="modal fade" id="cardModal">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form wire:submit="saveCard">
                <div class="modal-header"><h6 class="modal-title">{{ $cardId ? 'ویرایش کارت' : 'افزودن کارت' }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-start">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">عنوان (اختیاری)</label>
                            <input type="text" wire:model="cardTitle" class="form-control @error('cardTitle') is-invalid @enderror">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نام بانک</label>
                            <input type="text" wire:model="bankName" class="form-control @error('bankName') is-invalid @enderror">
                            @error('bankName') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">شماره کارت (۱۶ رقم)</label>
                            <input type="text" inputmode="numeric" dir="ltr" wire:model="cardNumber" class="form-control text-center @error('cardNumber') is-invalid @enderror">
                            @error('cardNumber') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نام صاحب حساب</label>
                            <input type="text" wire:model="ownerName" class="form-control @error('ownerName') is-invalid @enderror">
                            @error('ownerName') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">شماره شبا (اختیاری)</label>
                            <input type="text" dir="ltr" wire:model="sheba" placeholder="IR..." class="form-control @error('sheba') is-invalid @enderror">
                            @error('sheba') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="c-active" wire:model="cardActive">
                                <label class="form-check-label" for="c-active">نمایش در صفحه پرداخت</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">ذخیره</button><button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button></div>
            </form>
        </div></div>
    </div>
</div>
