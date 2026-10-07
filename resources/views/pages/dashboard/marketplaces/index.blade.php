<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Marketplaces\Capability;
use App\Marketplaces\Jobs\PullOrdersJob;
use App\Marketplaces\MarketplaceManager;
use App\Marketplaces\Services\SyncService;
use App\Models\Marketplace;
use Illuminate\Support\Str;

new class extends Component
{
    // ---- فرم تنظیمات ----
    public ?int $editId = null;
    public string $title = '';
    public array $credentials = [];     // ورودی جدید (خالی = بدون تغییر)
    public array $settings = [];
    public bool $isActive = false;
    public bool $autoSync = true;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('marketplaces.view'), 403);
    }

    protected function authorizeEdit(): void
    {
        abort_if(!auth()->user()->can('marketplaces.edit'), 403);
    }

    protected function manager(): MarketplaceManager
    {
        return app(MarketplaceManager::class);
    }

    protected function sync(): SyncService
    {
        return app(SyncService::class);
    }

    #[Computed]
    public function marketplaces()
    {
        return Marketplace::query()
            ->withCount([
                'listings',
                'listings as failed_listings_count' => fn ($q) => $q->where('sync_status', 'failed'),
                'orders as open_orders_count' => fn ($q) => $q->whereIn('status', ['new', 'processing']),
            ])
            ->orderBy('id')
            ->get();
    }

    // Providerهای ثبت‌شده در config که هنوز ردیف ندارند (افزودن مارکت‌پلیس جدید)
    #[Computed]
    public function missingProviders(): array
    {
        $existing = Marketplace::pluck('provider')->all();

        return collect($this->manager()->providers())->reject(fn ($p, $key) => in_array($key, $existing, true))->all();
    }

    public function addProvider(string $provider): void
    {
        $this->authorizeEdit();

        if (!$this->manager()->has($provider) || Marketplace::where('provider', $provider)->exists()) {
            return;
        }

        Marketplace::create([
            'provider' => $provider,
            'title' => $this->manager()->provider($provider)->label(),
            'is_active' => false,
            'auto_sync' => true,
            'webhook_secret' => Str::random(40),
        ]);

        unset($this->marketplaces, $this->missingProviders);
    }

    #[Computed]
    public function editing(): ?Marketplace
    {
        return $this->editId ? Marketplace::find($this->editId) : null;
    }

    #[Computed]
    public function fields(): array
    {
        $marketplace = $this->editing;

        if (!$marketplace || !$marketplace->hasDriver()) {
            return ['credentials' => [], 'settings' => []];
        }

        return [
            'credentials' => $marketplace->driver()->credentialFields(),
            'settings' => collect($marketplace->driver()->settingFields())->groupBy(fn ($f) => $f['group'] ?? 'عمومی', true)->map->all()->all(),
        ];
    }

    // نام فیلدهای محرمانه ذخیره‌شده (فقط نام، نه مقدار)
    #[Computed]
    public function storedCredentialKeys(): array
    {
        return array_keys(array_filter((array) $this->editing?->credentials, fn ($v) => filled($v)));
    }

    public function edit(int $id): void
    {
        $marketplace = Marketplace::findOrFail($id);
        $this->resetErrorBag();
        $this->editId = $marketplace->id;
        $this->title = $marketplace->title;
        $this->credentials = [];   // مقدار محرمانه هرگز به فرم برگردانده نمی‌شود
        $this->isActive = $marketplace->is_active;
        $this->autoSync = $marketplace->auto_sync;

        $this->settings = $marketplace->hasDriver()
            ? collect($marketplace->driver()->settingFields())->mapWithKeys(fn ($f, $key) => [$key => $marketplace->setting($key)])->all()
            : [];

        unset($this->editing, $this->fields, $this->storedCredentialKeys);
    }

    public function save(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'title' => ['required', 'string', 'max:100'],
            'credentials' => ['array'],
            'credentials.*' => ['nullable', 'string', 'max:5000'],
            'settings' => ['array'],
        ], [
            'title.required' => 'عنوان الزامی است.',
            'title.max' => 'عنوان حداکثر ۱۰۰ کاراکتر است.',
            'credentials.*.max' => 'مقدار وارد شده بیش از حد طولانی است.',
        ]);

        $marketplace = Marketplace::findOrFail($this->editId);
        abort_unless($marketplace->hasDriver(), 404);
        $driver = $marketplace->driver();

        // ادغام: فیلد خالی = مقدار قبلی حفظ شود
        $credentials = (array) $marketplace->credentials;
        foreach ($driver->credentialFields() as $key => $field) {
            $value = trim((string) ($this->credentials[$key] ?? ''));
            if ($value !== '') {
                $credentials[$key] = $value;
            }
        }

        $settings = [];
        foreach ($driver->settingFields() as $key => $field) {
            $value = $this->settings[$key] ?? ($field['default'] ?? null);

            $settings[$key] = match ($field['type'] ?? 'text') {
                'boolean' => (bool) $value,
                'number' => is_numeric($value) ? max(0, $value + 0) : ($field['default'] ?? null),
                'select' => array_key_exists((string) $value, (array) ($field['options'] ?? [])) ? (string) $value : ($field['default'] ?? null),
                'textarea' => is_string($value) ? mb_substr(trim($value), 0, 5000) : $value,
                default => is_string($value) ? mb_substr(trim($value), 0, 255) : $value,
            };

            if (($field['type'] ?? 'text') === 'url' && filled($settings[$key]) && !filter_var($settings[$key], FILTER_VALIDATE_URL)) {
                $this->addError('settings.' . $key, '«' . $field['label'] . '» باید یک آدرس معتبر باشد.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $marketplace->fill([
            'title' => trim($this->title),
            'credentials' => $credentials,
            'settings' => $settings,
            'auto_sync' => $this->autoSync,
        ]);

        // اطلاعات ناقص قابل ذخیره است، اما مارکت‌پلیس تا تکمیل آن فعال نمی‌شود
        if ($this->isActive && !$driver->isConfigured($marketplace)) {
            foreach ($driver->credentialFields() as $key => $field) {
                if (($field['required'] ?? false) && blank($credentials[$key] ?? null)) {
                    $this->addError('credentials.' . $key, '«' . $field['label'] . '» برای فعال‌سازی الزامی است.');
                }
            }
            foreach ($driver->settingFields() as $key => $field) {
                if (($field['required'] ?? false) && blank($settings[$key] ?? null)) {
                    $this->addError('settings.' . $key, '«' . $field['label'] . '» برای فعال‌سازی الزامی است.');
                }
            }
            if ($this->getErrorBag()->isEmpty()) {
                $this->addError('isActive', 'اطلاعات اتصال برای فعال‌سازی کامل نیست.');
            }

            return;
        }

        $credentialsChanged = $marketplace->isDirty('credentials');
        $marketplace->is_active = $this->isActive;
        $marketplace->save();

        if ($credentialsChanged) {
            $marketplace->forceFill(['connection_status' => 'unknown', 'connection_message' => null])->save();
        }

        $this->credentials = [];
        unset($this->marketplaces, $this->editing, $this->storedCredentialKeys);
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'تنظیمات ' . $marketplace->title . ' ذخیره شد.');
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeEdit();
        $marketplace = Marketplace::findOrFail($id);

        if (!$marketplace->is_active && !$marketplace->isConfigured()) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'ابتدا اطلاعات اتصال ' . $marketplace->title . ' را تکمیل کنید.');
            return;
        }

        $marketplace->update(['is_active' => !$marketplace->is_active]);
        unset($this->marketplaces);
    }

    public function toggleAutoSync(int $id): void
    {
        $this->authorizeEdit();
        $marketplace = Marketplace::findOrFail($id);
        $marketplace->update(['auto_sync' => !$marketplace->auto_sync]);
        unset($this->marketplaces);
    }

    public function testConnection(int $id): void
    {
        $this->authorizeEdit();
        $result = $this->sync()->testConnection(Marketplace::findOrFail($id));
        unset($this->marketplaces);

        $this->dispatch('alert', type: $result->ok ? 'success' : 'error', title: $result->ok ? 'اتصال برقرار است' : 'اتصال ناموفق', text: $result->message);
    }

    public function syncNow(int $id, string $type): void
    {
        $this->authorizeEdit();
        $marketplace = Marketplace::findOrFail($id);

        if (!$marketplace->isUsable()) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: $marketplace->title . ' فعال یا تنظیم نشده است.');
            return;
        }

        if ($type === 'orders') {
            if (!$marketplace->supports(Capability::PULL_ORDERS)) {
                return;
            }
            PullOrdersJob::dispatch($marketplace->id);
            $text = 'دریافت سفارش‌ها در صف قرار گرفت.';
        } else {
            $only = match ($type) {
                'stock' => ['price', 'stock', 'status'],
                'content' => ['content'],
                default => null,
            };
            $count = $this->sync()->queueMarketplace($marketplace, $only, $type === 'force');
            $text = $count . ' اتصال برای همگام‌سازی در صف قرار گرفت.';
        }

        $this->dispatch('alert', type: 'success', title: 'همگام‌سازی', text: $text);
    }

    public function regenerateSecret(int $id): void
    {
        $this->authorizeEdit();
        Marketplace::findOrFail($id)->update(['webhook_secret' => Str::random(40)]);
        unset($this->marketplaces);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'آدرس وب‌هوک جدید ساخته شد؛ آن را در پنل مارکت‌پلیس به‌روز کنید.');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">مارکت‌پلیس‌ها</h1>
            <div class="text-muted small">اتصال فروشگاه به دیجی‌کالا، باسلام، ترب و ... — همگام‌سازی محصول، قیمت، موجودی و سفارش</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('marketplaces.listings') }}" class="btn btn-primary-light btn-wave"><i class="ri-links-line align-middle"></i> اتصال محصولات</a>
            <a href="{{ route('marketplaces.orders') }}" class="btn btn-success-light btn-wave"><i class="ri-shopping-bag-3-line align-middle"></i> سفارش‌ها</a>
            <a href="{{ route('marketplaces.logs') }}" class="btn btn-light btn-wave"><i class="ri-file-list-3-line align-middle"></i> لاگ‌ها</a>
        </div>
    </div>

    <div class="row">
        @foreach($this->marketplaces as $marketplace)
            @php
                $driver = $marketplace->hasDriver() ? $marketplace->driver() : null;
                $configured = $marketplace->isConfigured();
                $caps = $driver?->capabilities() ?? [];
            @endphp
            <div class="col-xl-4 col-md-6" wire:key="mp-{{ $marketplace->id }}">
                <div class="card custom-card h-100">
                    <div class="card-header justify-content-between">
                        <div>
                            <div class="card-title">{{ $marketplace->title }}</div>
                            <div class="small text-muted"><code>{{ $marketplace->provider }}</code></div>
                        </div>
                        <div class="d-flex gap-1 align-items-center">
                            @if(!$driver)
                                <span class="badge bg-danger-transparent">بدون Adapter</span>
                            @elseif(!$configured)
                                <span class="badge bg-warning-transparent">تنظیم نشده</span>
                            @endif
                            <span @can('marketplaces.edit') role="button" wire:click="toggleActive({{ $marketplace->id }})" @endcan
                                  class="badge bg-outline-{{ $marketplace->is_active ? 'success' : 'secondary' }}">
                                {{ $marketplace->is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($driver)
                            <p class="small text-muted mb-2">{{ $driver->description() }}</p>
                            <div class="d-flex flex-wrap gap-1 mb-3">
                                @foreach($caps as $cap)
                                    <span class="badge bg-light text-dark border">{{ Capability::LABELS[$cap] ?? $cap }}</span>
                                @endforeach
                            </div>
                        @endif

                        <ul class="list-unstyled small mb-3">
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">وضعیت اتصال</span>
                                <span>
                                    @if($marketplace->connection_status === 'connected')
                                        <span class="text-success"><i class="ri-checkbox-circle-line"></i> متصل</span>
                                    @elseif($marketplace->connection_status === 'failed')
                                        <span class="text-danger"><i class="ri-error-warning-line"></i> ناموفق</span>
                                    @else
                                        <span class="text-muted">بررسی نشده</span>
                                    @endif
                                </span>
                            </li>
                            @if($marketplace->connection_message)
                                <li class="py-1 border-bottom text-muted text-wrap">{{ $marketplace->connection_message }}
                                    @if($marketplace->connection_checked_at)<span class="d-block">({{ verta($marketplace->connection_checked_at)->format('Y/m/d H:i') }})</span>@endif
                                </li>
                            @endif
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">{{ in_array(Capability::PRODUCT_FEED, $caps, true) ? 'آخرین خواندن محصولات' : 'آخرین Sync محصول' }}</span>
                                <span>{{ $marketplace->last_product_sync_at ? verta($marketplace->last_product_sync_at)->formatDifference() : '—' }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">آخرین Sync قیمت/موجودی</span>
                                <span>{{ $marketplace->last_stock_sync_at ? verta($marketplace->last_stock_sync_at)->formatDifference() : '—' }}</span>
                            </li>
                            @if(array_intersect([Capability::PULL_ORDERS, Capability::WEBHOOK], $caps))
                                <li class="d-flex justify-content-between py-1 border-bottom">
                                    <span class="text-muted">آخرین دریافت سفارش</span>
                                    <span>{{ $marketplace->last_order_sync_at ? verta($marketplace->last_order_sync_at)->formatDifference() : '—' }}</span>
                                </li>
                            @endif
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">محصولات متصل</span>
                                <span>
                                    <a href="{{ route('marketplaces.listings', ['marketplace' => $marketplace->id]) }}">{{ number_format($marketplace->listings_count) }}</a>
                                    @if($marketplace->failed_listings_count)
                                        <a href="{{ route('marketplaces.listings', ['marketplace' => $marketplace->id, 'status' => 'failed']) }}" class="badge bg-danger ms-1">{{ $marketplace->failed_listings_count }} خطا</a>
                                    @endif
                                </span>
                            </li>
                            @if(array_intersect([Capability::PULL_ORDERS, Capability::WEBHOOK], $caps))
                                <li class="d-flex justify-content-between py-1 border-bottom">
                                    <span class="text-muted">سفارش‌های باز</span>
                                    <a href="{{ route('marketplaces.orders', ['marketplace' => $marketplace->id]) }}">{{ number_format($marketplace->open_orders_count) }}</a>
                                </li>
                            @endif
                            <li class="d-flex justify-content-between py-1">
                                <span class="text-muted">همگام‌سازی خودکار</span>
                                <span @can('marketplaces.edit') role="button" wire:click="toggleAutoSync({{ $marketplace->id }})" @endcan
                                      class="badge bg-{{ $marketplace->auto_sync ? 'primary' : 'secondary' }}-transparent">{{ $marketplace->auto_sync ? 'روشن' : 'خاموش' }}</span>
                            </li>
                        </ul>

                        @if($marketplace->last_error && $marketplace->last_error_at && $marketplace->last_error_at->gt(now()->subDay()))
                            <div class="alert alert-danger small py-2 mb-3">
                                <i class="ri-error-warning-line"></i> {{ $marketplace->last_error }}
                                <span class="d-block text-muted">{{ verta($marketplace->last_error_at)->formatDifference() }}</span>
                            </div>
                        @endif

                        @if(in_array(Capability::PRODUCT_FEED, $caps, true))
                            <label class="form-label small text-muted mb-1">آدرس API محصولات (برای ثبت در پنل {{ $marketplace->title }})</label>
                            <input type="text" readonly class="form-control form-control-sm mb-2" dir="ltr" value="{{ app(\App\Marketplaces\MarketplaceManager::class)->feedUrl($marketplace) }}" onclick="this.select()">
                        @endif
                        @if(in_array(Capability::WEBHOOK, $caps, true))
                            @can('marketplaces.edit')
                                <label class="form-label small text-muted mb-1">آدرس وب‌هوک (برای ثبت در پنل {{ $marketplace->title }})</label>
                                <div class="input-group input-group-sm mb-2">
                                    <input type="text" readonly class="form-control" dir="ltr" value="{{ app(\App\Marketplaces\MarketplaceManager::class)->webhookUrl($marketplace) }}" onclick="this.select()">
                                    <button type="button" class="btn btn-light" title="ساخت آدرس جدید" wire:click="regenerateSecret({{ $marketplace->id }})" wire:confirm="آدرس فعلی وب‌هوک از کار می‌افتد. ادامه می‌دهید؟"><i class="ri-refresh-line"></i></button>
                                </div>
                            @endcan
                        @endif

                        @if($driver && $driver->limitations())
                            <details class="small mt-2">
                                <summary class="text-muted">محدودیت‌های API رسمی</summary>
                                <ul class="mt-2 mb-0 ps-3 text-muted">
                                    @foreach($driver->limitations() as $limitation)
                                        <li class="mb-1">{{ $limitation }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                    @can('marketplaces.edit')
                        <div class="card-footer d-flex flex-wrap gap-2">
                            <a href="#marketplaceModal" data-bs-toggle="modal" wire:click="edit({{ $marketplace->id }})" class="btn btn-sm btn-primary-light"><i class="ri-settings-3-line"></i> تنظیمات</a>
                            @if($driver)
                                <button type="button" class="btn btn-sm btn-info-light" wire:click="testConnection({{ $marketplace->id }})" wire:loading.attr="disabled" wire:target="testConnection({{ $marketplace->id }})">
                                    <span wire:loading wire:target="testConnection({{ $marketplace->id }})" class="spinner-border spinner-border-sm"></span>
                                    <i class="ri-plug-line"></i> تست اتصال
                                </button>
                                @if($marketplace->isUsable())
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-success-light dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="ri-refresh-line"></i> Sync دستی</button>
                                        <ul class="dropdown-menu">
                                            @if(array_intersect([Capability::UPDATE_PRICE, Capability::UPDATE_STOCK], $caps))
                                                <li><a class="dropdown-item" href="javascript:void(0)" wire:click="syncNow({{ $marketplace->id }}, 'stock')">قیمت و موجودی (تغییرات)</a></li>
                                            @endif
                                            @if(array_intersect(Capability::LISTING_SYNC, $caps) || in_array(Capability::CREATE_LISTING, $caps, true))
                                                <li><a class="dropdown-item" href="javascript:void(0)" wire:click="syncNow({{ $marketplace->id }}, 'all')">همه اطلاعات محصولات (تغییرات)</a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0)" wire:click="syncNow({{ $marketplace->id }}, 'force')" wire:confirm="همه اطلاعات همه محصولات دوباره ارسال شود؟">ارسال کامل دوباره</a></li>
                                            @endif
                                            @if(in_array(Capability::PULL_ORDERS, $caps, true))
                                                <li><a class="dropdown-item" href="javascript:void(0)" wire:click="syncNow({{ $marketplace->id }}, 'orders')">دریافت سفارش‌ها</a></li>
                                            @endif
                                        </ul>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endcan
                </div>
            </div>
        @endforeach
    </div>

    @can('marketplaces.edit')
        @if($this->missingProviders)
            <div class="card custom-card">
                <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted small">افزودن مارکت‌پلیس:</span>
                    @foreach($this->missingProviders as $key => $provider)
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addProvider('{{ $key }}')"><i class="ri-add-line"></i> {{ $provider->label() }}</button>
                    @endforeach
                </div>
            </div>
        @endif
    @endcan

    <div class="alert alert-info small">
        همگام‌سازی خودکار با صف (Queue) و زمان‌بندی (<code>php artisan schedule:run</code>) انجام می‌شود؛ روی سرور باید <code>queue:work</code> و cron زمان‌بندی فعال باشند.
        تغییر موجودی انبار، قیمت یا وضعیت محصول به‌صورت خودکار به مارکت‌پلیس‌های متصل ارسال و قیمت/موجودی هر ساعت دوباره تطبیق داده می‌شود.
    </div>

    {{-- ===================== مودال تنظیمات ===================== --}}
    <div wire:ignore.self class="modal fade" id="marketplaceModal">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable"><div class="modal-content">
            <form wire:submit="save" autocomplete="off">
                <div class="modal-header"><h6 class="modal-title">تنظیمات {{ $this->editing?->title }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-start">
                    @if($this->editing)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">عنوان</label>
                                <input type="text" wire:model="title" class="form-control @error('title') is-invalid @enderror">
                                @error('title') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="mp-active" wire:model="isActive">
                                    <label class="form-check-label" for="mp-active">فعال</label>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="mp-auto" wire:model="autoSync">
                                    <label class="form-check-label" for="mp-auto">همگام‌سازی خودکار</label>
                                </div>
                            </div>
                            @error('isActive') <div class="col-12"><div class="alert alert-danger small py-2 mb-0">{{ $message }}</div></div> @enderror

                            @if($this->fields['credentials'])
                                <div class="col-12"><h6 class="fw-semibold border-bottom pb-2 mb-0 mt-2">اطلاعات اتصال (محرمانه)</h6></div>
                                @foreach($this->fields['credentials'] as $key => $field)
                                    @php $stored = in_array($key, $this->storedCredentialKeys, true); @endphp
                                    <div class="{{ ($field['type'] ?? '') === 'textarea' ? 'col-12' : 'col-md-6' }}" wire:key="cred-{{ $editId }}-{{ $key }}">
                                        <label class="form-label">{{ $field['label'] }} @if($field['required'] ?? false)<span class="text-danger">*</span>@endif</label>
                                        @if(($field['type'] ?? '') === 'textarea')
                                            <textarea wire:model="credentials.{{ $key }}" rows="4" dir="ltr"
                                                      class="form-control font-monospace small @error('credentials.' . $key) is-invalid @enderror"
                                                      placeholder="{{ $stored ? 'ذخیره شده (برای تغییر، مقدار جدید وارد کنید)' : '' }}"></textarea>
                                        @else
                                            <input type="password" wire:model="credentials.{{ $key }}" autocomplete="new-password" dir="ltr"
                                                   class="form-control @error('credentials.' . $key) is-invalid @enderror"
                                                   placeholder="{{ $stored ? '•••••••• ذخیره شده (برای تغییر، مقدار جدید وارد کنید)' : '' }}">
                                        @endif
                                        @if(!empty($field['help']))<div class="form-text">{{ $field['help'] }}</div>@endif
                                        @error('credentials.' . $key) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                @endforeach
                            @endif

                            @foreach($this->fields['settings'] as $group => $groupFields)
                                <div class="col-12"><h6 class="fw-semibold border-bottom pb-2 mb-0 mt-2">{{ $group }}</h6></div>
                                @foreach($groupFields as $key => $field)
                                    @php $type = $field['type'] ?? 'text'; @endphp
                                    <div class="{{ $type === 'textarea' ? 'col-12' : 'col-md-6' }}" wire:key="set-{{ $editId }}-{{ $key }}">
                                        @if($type === 'boolean')
                                            <div class="form-check form-switch mt-md-4">
                                                <input class="form-check-input" type="checkbox" id="ms-{{ $key }}" wire:model="settings.{{ $key }}">
                                                <label class="form-check-label" for="ms-{{ $key }}">{{ $field['label'] }}</label>
                                            </div>
                                        @elseif($type === 'select')
                                            <label class="form-label">{{ $field['label'] }}</label>
                                            <select wire:model="settings.{{ $key }}" class="form-select">
                                                @foreach($field['options'] ?? [] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($type === 'textarea')
                                            <label class="form-label">{{ $field['label'] }}</label>
                                            <textarea wire:model="settings.{{ $key }}" rows="3" class="form-control"></textarea>
                                        @else
                                            <label class="form-label">{{ $field['label'] }} @if($field['required'] ?? false)<span class="text-danger">*</span>@endif</label>
                                            <input type="{{ $type === 'number' ? 'number' : 'text' }}" @if(in_array($type, ['url', 'number'], true) || str_ends_with($key, '_path') || str_ends_with($key, '_field')) dir="ltr" @endif
                                                   wire:model="settings.{{ $key }}" class="form-control @error('settings.' . $key) is-invalid @enderror">
                                        @endif
                                        @if(!empty($field['help']))<div class="form-text">{{ $field['help'] }}</div>@endif
                                        @error('settings.' . $key) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                        <div class="alert alert-info small mt-3 mb-0">
                            اطلاعات محرمانه رمزنگاری‌شده ذخیره می‌شوند و در فرم نمایش داده نمی‌شوند. اطلاعات ناقص قابل ذخیره است، اما مارکت‌پلیس تا تکمیل فیلدهای ستاره‌دار فعال نمی‌شود.
                        </div>
                    @endif
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">ذخیره</button><button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button></div>
            </form>
        </div></div>
    </div>
</div>
