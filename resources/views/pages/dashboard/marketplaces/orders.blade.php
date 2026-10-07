<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Marketplaces\Capability;
use App\Marketplaces\Exceptions\MarketplaceException;
use App\Marketplaces\Jobs\PullOrdersJob;
use App\Marketplaces\Services\OrderImporter;
use App\Marketplaces\Services\SyncService;
use App\Models\Marketplace;
use App\Models\MarketplaceOrder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';

    #[Url(except: '')]
    public string $marketplace = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'stock', except: '')]
    public string $stockStatus = '';

    #[Url(except: '')]
    public string $search = '';

    public ?int $selectedId = null;
    public string $newStatus = '';
    public string $note = '';
    public string $action = '';
    public array $actionInput = [];

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('marketplaces.view'), 403);
    }

    protected function authorizeEdit(): void
    {
        abort_if(!auth()->user()->can('marketplaces.edit'), 403);
    }

    public function updated($property): void
    {
        if (in_array($property, ['marketplace', 'status', 'stockStatus', 'search'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function marketplaces()
    {
        return Marketplace::orderBy('id')->get()
            ->filter(fn ($m) => $m->supports(Capability::PULL_ORDERS) || $m->supports(Capability::WEBHOOK))
            ->values();
    }

    #[Computed]
    public function data()
    {
        $term = trim($this->search);

        return MarketplaceOrder::query()
            ->with('marketplace:id,title,provider')
            ->withCount('items')
            ->when($this->marketplace !== '', fn ($q) => $q->where('marketplace_id', (int) $this->marketplace))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->stockStatus !== '', fn ($q) => $q->where('stock_status', $this->stockStatus))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('external_id', 'like', "%{$term}%")
                ->orWhere('external_order_id', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('customer_mobile', 'like', "%{$term}%")
                ->orWhere('tracking_code', 'like', "%{$term}%")))
            ->latest('ordered_at')
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function selected(): ?MarketplaceOrder
    {
        return $this->selectedId
            ? MarketplaceOrder::with(['marketplace', 'items.variant.product', 'items.listing', 'logs'])->find($this->selectedId)
            : null;
    }

    #[Computed]
    public function actions(): array
    {
        $order = $this->selected;

        if (!$order || !$order->marketplace?->isUsable() || !$order->marketplace->supports(Capability::ORDER_ACTIONS)) {
            return [];
        }

        return $order->marketplace->driver()->orderActions($order->marketplace, $order);
    }

    public function show(int $id): void
    {
        $this->selectedId = $id;
        $this->resetErrorBag();
        $this->reset(['note', 'action', 'actionInput']);
        $this->newStatus = (string) $this->selected?->status;
        $this->note = (string) $this->selected?->admin_note;
        unset($this->selected, $this->actions);
    }

    public function pullNow(): void
    {
        $this->authorizeEdit();
        $count = 0;

        foreach ($this->marketplaces as $m) {
            if ($m->isUsable() && $m->supports(Capability::PULL_ORDERS) && ($this->marketplace === '' || (int) $this->marketplace === $m->id)) {
                PullOrdersJob::dispatch($m->id);
                $count++;
            }
        }

        $this->dispatch('alert', type: $count ? 'success' : 'warning', title: 'دریافت سفارش‌ها', text: $count ? 'دریافت سفارش‌ها در صف قرار گرفت.' : 'مارکت‌پلیس فعالی با امکان دریافت سفارش وجود ندارد.');
    }

    public function refreshOrder(): void
    {
        $this->authorizeEdit();

        try {
            app(SyncService::class)->refreshOrder($this->selected);
            $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'سفارش از مارکت‌پلیس به‌روز شد.');
        } catch (MarketplaceException $e) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: $e->getMessage());
        }

        unset($this->selected, $this->actions, $this->data);
    }

    /** ثبت وضعیت در پنل فروشگاه (مثلاً لغو سفارشی که وب‌هوک مارکت‌پلیس اعلام نمی‌کند) */
    public function saveStatus(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'newStatus' => ['required', Rule::in(array_keys(MarketplaceOrder::STATUSES))],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'newStatus.required' => 'وضعیت را انتخاب کنید.',
            'newStatus.in' => 'وضعیت معتبر نیست.',
        ]);

        try {
            app(OrderImporter::class)->setStatus($this->selected, $this->newStatus, $this->note);
        } catch (ValidationException $e) {
            $this->addError('newStatus', collect($e->errors())->flatten()->first());
            return;
        }

        app(\App\Marketplaces\MarketplaceManager::class)
            ->client($this->selected->marketplace, ['order_id' => $this->selectedId])
            ->log('order.status', 'success', 'تغییر وضعیت دستی به «' . MarketplaceOrder::STATUSES[$this->newStatus] . '» توسط ' . (trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) ?: auth()->user()->mobile), ['note' => $this->note], 'in');

        unset($this->selected, $this->actions, $this->data);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'وضعیت سفارش ذخیره شد.');
    }

    public function reconcileStock(): void
    {
        $this->authorizeEdit();

        try {
            app(OrderImporter::class)->reconcileStock($this->selected);
        } catch (ValidationException $e) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: collect($e->errors())->flatten()->first());
            return;
        }

        unset($this->selected, $this->data);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'موجودی انبار بر اساس وضعیت سفارش بررسی شد.');
    }

    public function chooseAction(string $action): void
    {
        $this->action = array_key_exists($action, $this->actions) ? $action : '';
        $this->actionInput = [];
        $this->resetErrorBag();
    }

    public function performAction(): void
    {
        $this->authorizeEdit();
        $definition = $this->actions[$this->action] ?? null;

        if (!$definition) {
            return;
        }

        foreach ($definition['fields'] ?? [] as $key => $field) {
            $value = trim((string) ($this->actionInput[$key] ?? ''));

            if (($field['required'] ?? false) && $value === '') {
                $this->addError('actionInput.' . $key, '«' . $field['label'] . '» الزامی است.');
            }

            if (($field['type'] ?? '') === 'select' && $value !== '' && !array_key_exists($value, (array) $field['options'])) {
                $this->addError('actionInput.' . $key, '«' . $field['label'] . '» معتبر نیست.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            app(SyncService::class)->performOrderAction($this->selected, $this->action, $this->actionInput);
        } catch (MarketplaceException $e) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: $e->getMessage());
            return;
        }

        $this->reset(['action', 'actionInput']);
        unset($this->selected, $this->actions, $this->data);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'وضعیت در مارکت‌پلیس ثبت شد.');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">سفارش‌های مارکت‌پلیس‌ها</h1>
            <div class="text-muted small">سفارش‌های دریافتی؛ پرداخت در خود مارکت‌پلیس انجام شده و موجودی انبار فروشگاه با ثبت سفارش کسر می‌شود</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('marketplaces.index') }}" class="btn btn-light btn-wave"><i class="ri-store-2-line align-middle"></i> مارکت‌پلیس‌ها</a>
            @can('marketplaces.edit')
                <button type="button" class="btn btn-primary btn-wave" wire:click="pullNow"><i class="ri-download-cloud-2-line"></i> دریافت سفارش‌ها</button>
            @endcan
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <select wire:model.live="marketplace" class="form-select form-select-sm">
                        <option value="">همه مارکت‌پلیس‌ها</option>
                        @foreach($this->marketplaces as $m)
                            <option value="{{ $m->id }}">{{ $m->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="status" class="form-select form-select-sm">
                        <option value="">همه وضعیت‌ها</option>
                        @foreach(MarketplaceOrder::STATUSES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="stockStatus" class="form-select form-select-sm">
                        <option value="">وضعیت انبار</option>
                        @foreach(MarketplaceOrder::STOCK_STATUSES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm" placeholder="شناسه سفارش، نام/موبایل خریدار، کد رهگیری">
                </div>
            </div>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap">
                    <thead><tr><th>شناسه</th><th>مارکت‌پلیس</th><th>تاریخ</th><th>خریدار</th><th>اقلام</th><th>مبلغ (تومان)</th><th>وضعیت</th><th>انبار</th><th></th></tr></thead>
                    <tbody>
                    @forelse($this->data as $order)
                        <tr wire:key="mo-{{ $order->id }}">
                            <td dir="ltr">{{ $order->external_id }}@if($order->external_order_id && $order->external_order_id !== $order->external_id)<div class="small text-muted">سفارش {{ $order->external_order_id }}</div>@endif</td>
                            <td>{{ $order->marketplace?->title }}</td>
                            <td class="small">{{ $order->ordered_at ? verta($order->ordered_at)->format('Y/m/d H:i') : verta($order->created_at)->format('Y/m/d H:i') }}</td>
                            <td>{{ $order->customer_name ?? '—' }}<div class="small text-muted" dir="ltr">{{ $order->customer_mobile }}</div></td>
                            <td>{{ $order->items_count }}</td>
                            <td>{{ number_format($order->total_amount) }}</td>
                            <td>
                                <span class="badge bg-{{ ['new' => 'primary', 'processing' => 'info', 'shipped' => 'secondary', 'delivered' => 'success', 'cancelled' => 'danger', 'returned' => 'warning', 'problem' => 'warning'][$order->status] ?? 'light' }}-transparent">{{ $order->status_label }}</span>
                                @if($order->external_status)<div class="small text-muted">{{ $order->external_status }}</div>@endif
                            </td>
                            <td><span class="badge bg-{{ ['applied' => 'success', 'partial' => 'danger', 'reverted' => 'secondary', 'skipped' => 'warning'][$order->stock_status] ?? 'light' }}-transparent">{{ $order->stock_status_label }}</span></td>
                            <td><a href="#marketplaceOrderModal" data-bs-toggle="modal" wire:click="show({{ $order->id }})" class="text-info"><i class="ri-eye-line"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">سفارشی دریافت نشده است.</td></tr>
                    @endforelse
                    <tr><td colspan="100">{{ $this->data->links() }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===================== جزئیات سفارش ===================== --}}
    <div wire:ignore.self class="modal fade" id="marketplaceOrderModal">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable"><div class="modal-content">
            @php $o = $this->selected; @endphp
            <div class="modal-header">
                <h6 class="modal-title">سفارش {{ $o?->marketplace?->title }} <span dir="ltr">{{ $o?->external_id }}</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-start">
                @if($o)
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">وضعیت</div><div class="fw-semibold">{{ $o->status_label }}</div><div class="small text-muted">{{ $o->external_status }}</div></div></div>
                        <div class="col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">پرداخت (در مارکت‌پلیس)</div><div class="fw-semibold">{{ MarketplaceOrder::PAYMENT_STATUSES[$o->payment_status] ?? $o->payment_status }}</div><div class="small text-muted">{{ $o->paid_at ? verta($o->paid_at)->format('Y/m/d H:i') : '' }}</div></div></div>
                        <div class="col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">مبلغ</div><div class="fw-semibold">{{ number_format($o->total_amount) }} تومان</div><div class="small text-muted">کالا {{ number_format($o->items_amount) }} + ارسال {{ number_format($o->shipping_amount) }}</div></div></div>
                        <div class="col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">انبار فروشگاه</div><div class="fw-semibold">{{ $o->stock_status_label }}</div>
                            @can('marketplaces.edit')<button type="button" class="btn btn-sm btn-link p-0" wire:click="reconcileStock">بررسی دوباره</button>@endcan
                        </div></div>
                    </div>

                    @if($o->customer_name || $o->address)
                        <div class="border rounded p-2 mb-3 small">
                            <div><span class="text-muted">گیرنده:</span> {{ $o->customer_name }} <span dir="ltr">{{ $o->customer_mobile }}</span></div>
                            <div><span class="text-muted">نشانی:</span> {{ $o->province }} {{ $o->city }} — {{ $o->address }} @if($o->postal_code)<span class="text-muted">(کد پستی {{ $o->postal_code }})</span>@endif</div>
                            @if($o->tracking_code)<div><span class="text-muted">کد رهگیری:</span> <span dir="ltr">{{ $o->tracking_code }}</span></div>@endif
                        </div>
                    @endif

                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>کالا</th><th>محصول فروشگاه</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th><th>کسر از انبار</th></tr></thead>
                            <tbody>
                            @foreach($o->items as $item)
                                <tr>
                                    <td>{{ $item->title }}<div class="small text-muted" dir="ltr">{{ $item->external_product_id }}@if($item->external_variant_id) / {{ $item->external_variant_id }}@endif</div></td>
                                    <td>
                                        @if($item->variant)
                                            {{ $item->variant->product?->title }} <span class="small text-muted">({{ $item->variant->sku ?: 'واریانت ' . $item->variant->id }})</span>
                                        @else
                                            <span class="badge bg-warning-transparent">متصل نیست</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ number_format($item->price) }}</td>
                                    <td>{{ number_format($item->total) }}</td>
                                    <td>{{ $item->stock_deducted }} / {{ $item->quantity }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    @can('marketplaces.edit')
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="fw-semibold">وضعیت در پنل فروشگاه</h6>
                                    <div class="mb-2">
                                        <select wire:model="newStatus" class="form-select form-select-sm @error('newStatus') is-invalid @enderror">
                                            @foreach(MarketplaceOrder::STATUSES as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('newStatus') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                    <textarea wire:model="note" rows="2" class="form-control form-control-sm mb-2" placeholder="یادداشت مدیر"></textarea>
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="saveStatus" wire:loading.attr="disabled">ذخیره وضعیت</button>
                                    <div class="form-text">لغو/مرجوعی موجودی را به انبار برمی‌گرداند. این تغییر فقط در فروشگاه ثبت می‌شود.</div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="fw-semibold">عملیات در {{ $o->marketplace?->title }}</h6>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <button type="button" class="btn btn-sm btn-info-light" wire:click="refreshOrder" wire:loading.attr="disabled" wire:target="refreshOrder"><i class="ri-refresh-line"></i> دریافت دوباره</button>
                                        @foreach($this->actions as $key => $definition)
                                            <button type="button" class="btn btn-sm {{ $action === $key ? 'btn-success' : 'btn-success-light' }}" wire:click="chooseAction('{{ $key }}')">{{ $definition['label'] }}</button>
                                        @endforeach
                                    </div>
                                    @if($action && isset($this->actions[$action]))
                                        @foreach($this->actions[$action]['fields'] ?? [] as $key => $field)
                                            <div class="mb-2">
                                                <label class="form-label small mb-1">{{ $field['label'] }}</label>
                                                @if(($field['type'] ?? '') === 'select')
                                                    <select wire:model="actionInput.{{ $key }}" class="form-select form-select-sm @error('actionInput.' . $key) is-invalid @enderror">
                                                        <option value="">انتخاب کنید</option>
                                                        @foreach($field['options'] as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="text" wire:model="actionInput.{{ $key }}" dir="ltr" class="form-control form-control-sm @error('actionInput.' . $key) is-invalid @enderror">
                                                @endif
                                                @error('actionInput.' . $key) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                            </div>
                                        @endforeach
                                        <button type="button" class="btn btn-sm btn-success" wire:click="performAction" wire:loading.attr="disabled" wire:target="performAction">ثبت در {{ $o->marketplace?->title }}</button>
                                    @elseif(!$this->actions)
                                        <div class="small text-muted">تغییر وضعیت سفارش از طریق API این مارکت‌پلیس پشتیبانی نمی‌شود یا برای وضعیت فعلی عملیاتی وجود ندارد.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endcan

                    <h6 class="fw-semibold mt-4">رویدادها</h6>
                    <ul class="list-unstyled small">
                        @forelse($o->logs->take(20) as $log)
                            <li class="border-bottom py-1">
                                <span class="badge bg-{{ $log->status === 'success' ? 'success' : 'danger' }}-transparent">{{ $log->operation_label }}</span>
                                {{ $log->message }} <span class="text-muted">— {{ verta($log->created_at)->format('Y/m/d H:i') }}</span>
                            </li>
                        @empty
                            <li class="text-muted">رویدادی ثبت نشده است.</li>
                        @endforelse
                    </ul>

                    @if($o->payload)
                        <details class="small">
                            <summary class="text-muted">داده خام مارکت‌پلیس</summary>
                            <pre class="bg-light p-2 rounded mt-2" dir="ltr" style="max-height: 300px; overflow: auto">{{ json_encode($o->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @endif
                @endif
            </div>
        </div></div>
    </div>
</div>
