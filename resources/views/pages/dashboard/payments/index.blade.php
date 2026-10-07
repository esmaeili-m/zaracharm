<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentGateway;
use App\Payments\PaymentService;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';

    #[Url(except: '')]
    public string $search = '';          // شماره سفارش / کد رهگیری / Authority / موبایل

    #[Url(except: '')]
    public string $method = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $gateway = '';

    #[Url(as: 'from', except: '')]
    public string $fromDate = '';

    #[Url(as: 'to', except: '')]
    public string $toDate = '';

    #[Url(as: 'min', except: '')]
    public string $minAmount = '';

    #[Url(as: 'max', except: '')]
    public string $maxAmount = '';

    public ?int $selectedId = null;
    public string $note = '';

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('payments.view'), 403);
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'method', 'status', 'gateway', 'fromDate', 'toDate', 'minAmount', 'maxAmount'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'method', 'status', 'gateway', 'fromDate', 'toDate', 'minAmount', 'maxAmount']);
        $this->resetPage();
    }

    #[Computed]
    public function methods(): array
    {
        return PaymentMethod::ordered()->pluck('title', 'key')->all();
    }

    #[Computed]
    public function gateways()
    {
        return PaymentGateway::withTrashed()->orderBy('sort')->get(['id', 'title', 'provider']);
    }

    protected function digits(?string $value): string
    {
        return strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', ',' => '', '٬' => '',
        ]);
    }

    protected function jalali(?string $value): ?\Carbon\Carbon
    {
        $value = str_replace('-', '/', $this->digits($value));

        if (!preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $value, $m)) {
            return null;
        }

        try {
            return \Morilog\Jalali\CalendarUtils::createCarbonFromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $m[1], $m[2], $m[3]));
        } catch (\Throwable) {
            return null;
        }
    }

    protected function query()
    {
        $from = $this->jalali($this->fromDate);
        $to = $this->jalali($this->toDate);
        $min = ctype_digit($this->digits($this->minAmount)) ? (int) $this->digits($this->minAmount) : null;
        $max = ctype_digit($this->digits($this->maxAmount)) ? (int) $this->digits($this->maxAmount) : null;
        $term = $this->digits($this->search);

        return Payment::query()
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    $w->where('reference', 'like', "%{$term}%")
                        ->orWhere('authority', 'like', "%{$term}%")
                        ->orWhere('transaction_id', 'like', "%{$term}%")
                        ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%"))
                        ->orWhereHas('user', fn ($u) => $u->where('mobile', 'like', "%{$term}%"));
                });
            })
            ->when($this->method !== '', fn ($q) => $q->where('method', $this->method))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->gateway !== '', fn ($q) => $q->where('gateway_id', $this->gateway))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->when($min !== null, fn ($q) => $q->where('amount', '>=', $min))
            ->when($max !== null, fn ($q) => $q->where('amount', '<=', $max));
    }

    #[Computed]
    public function summary(): array
    {
        $rows = $this->query()->toBase()
            ->selectRaw('status, COUNT(*) as c, COALESCE(SUM(amount), 0) as s')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'count' => (int) $rows->sum('c'),
            'paid' => (int) ($rows['paid']->s ?? 0),
            'pending' => (int) ($rows['pending']->c ?? 0),
            'review' => Payment::where('status', 'pending')->where('method', 'transfer')->count(),
        ];
    }

    #[Computed]
    public function data()
    {
        return $this->query()
            ->with(['order:id,order_number,status,payment_status', 'user:id,first_name,last_name,mobile', 'gatewayModel:id,title', 'bankCard:id,bank_name,card_number'])
            // پرداخت‌های کارت‌به‌کارت در انتظار بررسی اول
            ->orderByRaw("CASE WHEN status = 'pending' AND method = 'transfer' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function selected(): ?Payment
    {
        return $this->selectedId
            ? Payment::with(['order.items', 'order.address', 'user', 'gatewayModel', 'bankCard', 'reviewer', 'logs.user'])->find($this->selectedId)
            : null;
    }

    #[Computed]
    public function actions(): array
    {
        if (!$this->selected) {
            return [];
        }

        $allowed = app(PaymentService::class)->allowedTransitions($this->selected);
        $method = $this->selected->method;
        $actions = [];

        // تأیید دستی فقط برای کارت‌به‌کارت و پرداخت در محل (درگاه فقط با Verify تأیید می‌شود)
        if (in_array('paid', $allowed, true) && in_array($method, ['transfer', 'cod'], true) && $this->selected->isPending()) {
            $actions['approve'] = ['label' => $method === 'cod' ? 'ثبت دریافت وجه' : 'تأیید پرداخت', 'class' => 'success'];
        }
        if (in_array('rejected', $allowed, true) && $method === 'transfer') {
            $actions['reject'] = ['label' => 'رد پرداخت', 'class' => 'danger'];
        }
        if (in_array('cancelled', $allowed, true) && $method !== 'transfer') {
            $actions['cancel'] = ['label' => 'لغو پرداخت', 'class' => 'secondary'];
        }
        if (in_array('refunded', $allowed, true)) {
            $actions['refund'] = ['label' => 'ثبت بازگشت وجه', 'class' => 'info'];
        }

        return $actions;
    }

    public function show(int $id): void
    {
        $this->selectedId = $id;
        $this->note = '';
        $this->resetErrorBag();
        unset($this->selected, $this->actions);
    }

    public function act(string $action)
    {
        abort_if(!auth()->user()->can('payments.edit'), 403);

        if (!$this->selected || !array_key_exists($action, $this->actions)) {
            return;
        }

        if (in_array($action, ['reject', 'refund', 'cancel'], true) && blank($this->note)) {
            $this->addError('note', 'برای این عملیات، توضیح را وارد کنید.');
            return;
        }

        $service = app(PaymentService::class);
        $note = filled($this->note) ? mb_substr(trim($this->note), 0, 1000) : null;

        try {
            match ($action) {
                'approve' => $service->approve($this->selected, $note),
                'reject' => $service->reject($this->selected, $note),
                'cancel' => $service->cancel($this->selected, $note),
                'refund' => $service->refund($this->selected, $note),
            };
        } catch (ValidationException $e) {
            $this->addError('note', collect($e->errors())->flatten()->first());
            return;
        }

        unset($this->selected, $this->actions, $this->data, $this->summary);
        $this->note = '';

        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'وضعیت پرداخت به‌روزرسانی شد.');
    }

    public function statusBadge(?string $status): string
    {
        return PaymentStatus::tryFrom((string) $status)?->badge() ?? 'secondary';
    }

    public function methodLabel(?string $key): string
    {
        return $this->methods[$key] ?? $key ?? '—';
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">پرداخت‌ها و تراکنش‌ها</h1>
            <div class="text-muted small">بررسی پرداخت‌های آنلاین، کارت‌به‌کارت، کیف پول و پرداخت در محل</div>
        </div>
        @can('payment-settings.view')
            <a href="{{ route('payments.settings') }}" class="btn btn-primary-light btn-wave">
                <i class="ri-settings-3-line align-middle"></i> تنظیمات پرداخت
            </a>
        @endcan
    </div>

    @php
        $summary = $this->summary;
    @endphp
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card custom-card mb-0"><div class="card-body py-3"><div class="text-muted small">تعداد (فیلتر فعلی)</div><div class="fw-bold fs-16">{{ number_format($summary['count']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card custom-card mb-0"><div class="card-body py-3"><div class="text-muted small">جمع پرداخت‌شده</div><div class="fw-bold fs-16 text-success">{{ number_format($summary['paid']) }} <small class="text-muted">تومان</small></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card custom-card mb-0"><div class="card-body py-3"><div class="text-muted small">در انتظار</div><div class="fw-bold fs-16 text-warning">{{ number_format($summary['pending']) }}</div></div></div></div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('payments.index', ['method' => 'transfer', 'status' => 'pending']) }}" class="card custom-card mb-0 text-reset">
                <div class="card-body py-3"><div class="text-muted small">کارت‌به‌کارت منتظر بررسی</div><div class="fw-bold fs-16 text-danger">{{ number_format($summary['review']) }}</div></div>
            </a>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-header d-block">
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">جستجو</label>
                    <input type="search" wire:model.live.debounce.400ms="search" class="form-control form-control-sm" placeholder="شماره سفارش، کد رهگیری، موبایل...">
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small text-muted mb-1">روش پرداخت</label>
                    <select wire:model.live="method" class="form-select form-select-sm">
                        <option value="">همه</option>
                        @foreach($this->methods as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small text-muted mb-1">وضعیت</label>
                    <select wire:model.live="status" class="form-select form-select-sm">
                        <option value="">همه</option>
                        @foreach(\App\Enums\PaymentStatus::options() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label small text-muted mb-1">درگاه</label>
                    <select wire:model.live="gateway" class="form-select form-select-sm">
                        <option value="">همه</option>
                        @foreach($this->gateways as $g)
                            <option value="{{ $g->id }}">{{ $g->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-8">
                    <label class="form-label small text-muted mb-1">بازه تاریخ (شمسی)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" dir="ltr" wire:model.live.debounce.600ms="fromDate" class="form-control text-center" placeholder="از ۱۴۰۵/۰۱/۰۱">
                        <input type="text" dir="ltr" wire:model.live.debounce.600ms="toDate" class="form-control text-center" placeholder="تا ۱۴۰۵/۱۲/۲۹">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">بازه مبلغ (تومان)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="numeric" dir="ltr" wire:model.live.debounce.600ms="minAmount" class="form-control text-center" placeholder="حداقل">
                        <input type="text" inputmode="numeric" dir="ltr" wire:model.live.debounce.600ms="maxAmount" class="form-control text-center" placeholder="حداکثر">
                    </div>
                </div>
                <div class="col-lg-2 col-md-3">
                    <button type="button" wire:click="clearFilters" class="btn btn-light btn-sm w-100"><i class="ri-refresh-line"></i> پاک کردن فیلترها</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>سفارش</th>
                        <th>کاربر</th>
                        <th>روش</th>
                        <th>مبلغ</th>
                        <th>کد رهگیری</th>
                        <th>وضعیت</th>
                        <th>تاریخ</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody wire:loading.class="opacity-50">
                    @forelse($this->data as $payment)
                        <tr wire:key="payment-{{ $payment->id }}" @class(['table-warning' => $payment->isPending() && $payment->method === 'transfer'])>
                            <td class="text-muted">{{ $payment->id }}</td>
                            <td class="fw-semibold">{{ $payment->order?->order_number ?? '—' }}</td>
                            <td class="small">{{ $payment->user?->mobile }} {{ trim((string) $payment->user?->full_name) ? '- ' . $payment->user->full_name : '' }}</td>
                            <td>
                                {{ $this->methodLabel($payment->method) }}
                                @if($payment->gatewayModel)
                                    <div class="small text-muted">{{ $payment->gatewayModel->title }}</div>
                                @elseif($payment->bankCard)
                                    <div class="small text-muted">{{ $payment->bankCard->bank_name }}</div>
                                @endif
                            </td>
                            <td>{{ number_format($payment->amount) }}</td>
                            <td dir="ltr" class="small">{{ $payment->reference ?: '—' }}</td>
                            <td><span class="badge bg-{{ $this->statusBadge($payment->status) }}-transparent">{{ $payment->status_label }}</span></td>
                            <td class="small text-muted">{{ verta($payment->created_at)->format('Y/m/d H:i') }}</td>
                            <td>
                                <a href="#paymentModal" data-bs-toggle="modal" wire:click="show({{ $payment->id }})" class="text-info fs-14" title="جزئیات"><i class="ri-eye-line"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-5">پرداختی یافت نشد.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr><td colspan="100">{{ $this->data->links() }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- جزئیات پرداخت --}}
    <div wire:ignore.self class="modal fade" id="paymentModal">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">جزئیات پرداخت #{{ $this->selected?->id }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-start">
                    @if($p = $this->selected)
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">سفارش</div><div class="fw-semibold">{{ $p->order?->order_number }}</div><div class="small text-muted">{{ $p->order?->status }} / {{ $p->order?->payment_status }}</div></div></div>
                            <div class="col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">مبلغ</div><div class="fw-semibold">{{ number_format($p->amount) }} تومان</div></div></div>
                            <div class="col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">روش</div><div class="fw-semibold">{{ $this->methodLabel($p->method) }}</div><div class="small text-muted">{{ $p->gatewayModel?->title }}</div></div></div>
                            <div class="col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">وضعیت</div><span class="badge bg-{{ $this->statusBadge($p->status) }}">{{ $p->status_label }}</span>
                                @if($p->meta['duplicate_order_payment'] ?? false)
                                    <div class="small text-danger mt-1">پرداخت تکراری سفارش — نیاز به بازگشت وجه</div>
                                @endif
                            </div></div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="fw-bold mb-2">اطلاعات پرداخت</h6>
                                    <dl class="row small mb-0">
                                        <dt class="col-5">کد رهگیری</dt><dd class="col-7" dir="ltr">{{ $p->reference ?: '—' }}</dd>
                                        @if($p->authority)<dt class="col-5">Authority</dt><dd class="col-7 text-break" dir="ltr">{{ $p->authority }}</dd>@endif
                                        @if($p->card_pan)<dt class="col-5">کارت پرداخت‌کننده</dt><dd class="col-7" dir="ltr">{{ $p->card_pan }}</dd>@endif
                                        @if($p->payer_name)<dt class="col-5">نام واریزکننده</dt><dd class="col-7">{{ $p->payer_name }}</dd>@endif
                                        @if($p->payer_card)<dt class="col-5">۴ رقم آخر کارت مبدأ</dt><dd class="col-7" dir="ltr">{{ $p->payer_card }}</dd>@endif
                                        <dt class="col-5">ثبت</dt><dd class="col-7">{{ verta($p->created_at)->format('Y/m/d H:i') }}</dd>
                                        @if($p->paid_at)<dt class="col-5">پرداخت</dt><dd class="col-7">{{ verta($p->paid_at)->format('Y/m/d H:i') }}</dd>@endif
                                        @if($p->failure_reason)<dt class="col-5">علت</dt><dd class="col-7 text-danger">{{ $p->failure_reason }}</dd>@endif
                                        @if($p->reviewer)<dt class="col-5">بررسی توسط</dt><dd class="col-7">{{ $p->reviewer->mobile }} — {{ verta($p->reviewed_at)->format('Y/m/d H:i') }}</dd>@endif
                                        @if($p->admin_note)<dt class="col-5">یادداشت مدیر</dt><dd class="col-7">{{ $p->admin_note }}</dd>@endif
                                    </dl>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    @if($p->method === 'transfer')
                                        <h6 class="fw-bold mb-2">کارت مقصد</h6>
                                        @php
                                            $dest = $p->meta['card'] ?? null;
                                        @endphp
                                        <div class="small">{{ $p->bankCard?->bank_name ?? ($dest['bank'] ?? '—') }} — {{ $p->bankCard?->owner_name ?? ($dest['owner'] ?? '') }}</div>
                                        <div class="fw-semibold mt-1" dir="ltr">{{ $p->bankCard?->formatted_number ?? ($dest['number'] ?? '') }}</div>
                                        <hr>
                                    @endif
                                    <h6 class="fw-bold mb-2">مشتری و سفارش</h6>
                                    <div class="small">{{ $p->user?->mobile }} {{ $p->user?->full_name }}</div>
                                    @if($p->order?->address)
                                        <div class="small text-muted mt-1">{{ $p->order->address->province }}، {{ $p->order->address->city }}، {{ $p->order->address->address }}</div>
                                    @endif
                                    <ul class="small mt-2 mb-0">
                                        @foreach($p->order?->items ?? [] as $item)
                                            <li>{{ $item->product_name }} × {{ $item->quantity }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>

                        @if($this->actions)
                            @can('payments.edit')
                                <div class="border rounded p-3 mb-3 bg-light">
                                    <label class="form-label">توضیح / علت (برای رد، لغو و بازگشت وجه الزامی)</label>
                                    <textarea wire:model="note" rows="2" class="form-control @error('note') is-invalid @enderror"></textarea>
                                    @error('note') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        @foreach($this->actions as $key => $action)
                                            <button type="button" class="btn btn-sm btn-{{ $action['class'] }}"
                                                    wire:click="act('{{ $key }}')"
                                                    wire:confirm="از «{{ $action['label'] }}» مطمئن هستید؟"
                                                    wire:loading.attr="disabled" wire:target="act">
                                                {{ $action['label'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endcan
                        @endif

                        <h6 class="fw-bold">تاریخچه</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered small mb-0">
                                <thead><tr><th>زمان</th><th>رویداد</th><th>وضعیت</th><th>توضیح</th><th>کاربر</th><th>IP</th></tr></thead>
                                <tbody>
                                @foreach($p->logs as $log)
                                    <tr wire:key="log-{{ $log->id }}">
                                        <td class="text-nowrap">{{ verta($log->created_at)->format('Y/m/d H:i:s') }}</td>
                                        <td>{{ $log->event }}</td>
                                        <td class="text-nowrap">{{ $log->from_status ?? '—' }} ← {{ $log->to_status ?? '—' }}</td>
                                        <td>{{ $log->message }}</td>
                                        <td>{{ $log->user?->mobile ?? 'سیستم' }}</td>
                                        <td dir="ltr">{{ $log->ip }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">در حال بارگذاری...</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
