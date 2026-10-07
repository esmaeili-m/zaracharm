<?php

use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Invoice $model;

    public ?Invoice $selectedInvoice = null;

    /*
    |--------------------------------------------------------------------------
    | فیلترها (در URL نگه داشته می‌شوند تا با رفرش/اشتراک لینک حفظ شوند)
    |--------------------------------------------------------------------------
    */
    #[\Livewire\Attributes\Url(except: '')]
    public string $search = '';

    #[\Livewire\Attributes\Url(except: '')]
    public string $status = '';

    // online | manual | pos
    #[\Livewire\Attributes\Url(except: '')]
    public string $source = '';

    // روش پرداخت سفارش (wallet | transfer | gateway | cod)
    #[\Livewire\Attributes\Url(except: '')]
    public string $paymentMethod = '';

    #[\Livewire\Attributes\Url(as: 'user', except: null)]
    public ?int $userId = null;

    public string $userSearch = '';

    // تاریخ شمسی (۱۴۰۵/۰۱/۱۵) یا میلادی (2026/04/04)
    #[\Livewire\Attributes\Url(as: 'from', except: null)]
    public ?string $fromDate = null;

    #[\Livewire\Attributes\Url(as: 'to', except: null)]
    public ?string $toDate = null;

    #[\Livewire\Attributes\Url(as: 'min', except: null)]
    public $minAmount = null;

    #[\Livewire\Attributes\Url(as: 'max', except: null)]
    public $maxAmount = null;

    // newest | oldest | amount_desc | amount_asc
    #[\Livewire\Attributes\Url(except: 'newest')]
    public string $sort = 'newest';

    #[\Livewire\Attributes\Url(except: 15)]
    public int $perPage = 15;

    public bool $showAdvanced = false;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Invoice $model): void
    {
        abort_unless(auth()->user()?->can('invoices.view'), 403);

        $this->model = $model;

        // اگر فیلتر پیشرفته‌ای از URL آمده، پنل باز باشد
        $this->showAdvanced = (bool) ($this->userId || $this->source || $this->paymentMethod || $this->fromDate || $this->toDate || $this->minAmount || $this->maxAmount);
    }

    // هر تغییر فیلتر => صفحه اول
    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'source', 'paymentMethod', 'userId', 'fromDate', 'toDate', 'minAmount', 'maxAmount', 'sort', 'perPage'], true)) {
            $this->resetPage();
        }

        if ($property === 'perPage' && !in_array($this->perPage, [15, 30, 50, 100], true)) {
            $this->perPage = 15;
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'source', 'paymentMethod', 'userId', 'userSearch', 'fromDate', 'toDate', 'minAmount', 'maxAmount', 'sort']);
        $this->resetPage();
    }

    public function clearFilter(string $filter): void
    {
        $map = [
            'search' => ['search'], 'status' => ['status'], 'source' => ['source'], 'paymentMethod' => ['paymentMethod'],
            'user' => ['userId', 'userSearch'], 'date' => ['fromDate', 'toDate'], 'amount' => ['minAmount', 'maxAmount'],
        ];

        if (isset($map[$filter])) {
            $this->reset($map[$filter]);
            $this->resetPage();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | فیلتر کاربر
    |--------------------------------------------------------------------------
    */
    #[\Livewire\Attributes\Computed]
    public function filterUser(): ?\App\Models\User
    {
        return $this->userId ? \App\Models\User::find($this->userId, ['id', 'first_name', 'last_name', 'mobile']) : null;
    }

    #[\Livewire\Attributes\Computed]
    public function userResults()
    {
        $term = trim($this->userSearch);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return \App\Models\User::query()
            ->where(fn ($q) => $q->where('mobile', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%"))
            ->withCount('invoices')
            ->orderByDesc('invoices_count')
            ->limit(8)
            ->get(['id', 'first_name', 'last_name', 'mobile']);
    }

    public function filterByUser(int $id): void
    {
        $this->userId = $id;
        $this->userSearch = '';
        $this->showAdvanced = true;
        $this->resetPage();
    }

    /*
    |--------------------------------------------------------------------------
    | لیست‌ها
    |--------------------------------------------------------------------------
    */
    #[\Livewire\Attributes\Computed]
    public function statuses(): array
    {
        return Invoice::STATUSES;
    }

    #[\Livewire\Attributes\Computed]
    public function sources(): array
    {
        return Invoice::SOURCES;
    }

    #[\Livewire\Attributes\Computed]
    public function paymentMethods(): array
    {
        return [
            'wallet' => 'کیف پول',
            'transfer' => 'کارت به کارت',
            'gateway' => 'درگاه پرداخت',
            'cod' => 'پرداخت در محل',
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function sorts(): array
    {
        return [
            'newest' => 'جدیدترین',
            'oldest' => 'قدیمی‌ترین',
            'amount_desc' => 'بیشترین مبلغ',
            'amount_asc' => 'کمترین مبلغ',
        ];
    }

    protected function latinDigits(?string $value): string
    {
        return strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /**
     * تبدیل تاریخ ورودی (شمسی یا میلادی، با ارقام فارسی یا انگلیسی) به Carbon
     */
    protected function parseDate(?string $value): ?\Carbon\Carbon
    {
        $value = str_replace('-', '/', trim($this->latinDigits($value)));

        if (!preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $value, $m)) {
            return null;
        }

        $normalized = sprintf('%04d/%02d/%02d', $m[1], $m[2], $m[3]);

        try {
            return (int) $m[1] < 1700
                ? \Morilog\Jalali\CalendarUtils::createCarbonFromFormat('Y/m/d', $normalized)
                : \Carbon\Carbon::createFromFormat('Y/m/d', $normalized);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function amount($value): ?int
    {
        $value = str_replace([',', '٬', ' '], '', $this->latinDigits((string) $value));

        return $value !== '' && ctype_digit($value) ? (int) $value : null;
    }

    #[\Livewire\Attributes\Computed]
    public function dateError(): ?string
    {
        foreach (['fromDate' => 'از تاریخ', 'toDate' => 'تا تاریخ'] as $field => $label) {
            if (filled($this->{$field}) && !$this->parseDate($this->{$field})) {
                return "«{$label}» معتبر نیست؛ مثال: ۱۴۰۵/۰۱/۱۵";
            }
        }

        return null;
    }

    #[\Livewire\Attributes\Computed]
    public function hasActiveFilters(): bool
    {
        return trim($this->search) !== '' || $this->status !== '' || $this->source !== '' || $this->paymentMethod !== ''
            || $this->userId || filled($this->fromDate) || filled($this->toDate)
            || $this->amount($this->minAmount) !== null || $this->amount($this->maxAmount) !== null;
    }

    /**
     * کوئری فیلترشده (مشترک بین لیست و خلاصه آماری)
     */
    protected function filteredQuery()
    {
        $from = $this->parseDate($this->fromDate);
        $to = $this->parseDate($this->toDate);
        $min = $this->amount($this->minAmount);
        $max = $this->amount($this->maxAmount);
        $user = $this->filterUser;

        return $this->model
            ->newQuery()
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);

                $query->where(function ($q) use ($term) {
                    $q->where('invoice_number', 'like', "%{$term}%")
                        ->orWhereHas('order', function ($orderQuery) use ($term) {
                            $orderQuery->where('order_number', 'like', "%{$term}%");
                        })
                        ->orWhereHas('user', function ($userQuery) use ($term) {
                            $userQuery
                                ->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%")
                                ->orWhere('mobile', 'like', "%{$term}%");
                        })
                        // مشتری حضوری (بدون حساب کاربری)
                        ->orWhere('customer_name', 'like', "%{$term}%")
                        ->orWhere('customer_mobile', 'like', "%{$term}%");
                });
            })
            // کاربر: فاکتورهای حساب کاربر + فاکتورهای حضوری ثبت‌شده با همان موبایل
            ->when($user, function ($query) use ($user) {
                $query->where(fn ($q) => $q->where('user_id', $user->id)
                    ->orWhere(fn ($w) => $w->whereNull('user_id')->where('customer_mobile', $user->mobile)));
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->source !== '', fn ($query) => $query->where('source', $this->source))
            ->when($this->paymentMethod !== '', fn ($query) => $query->whereHas('order', fn ($o) => $o->where('payment_method', $this->paymentMethod)))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to->copy()->endOfDay()))
            ->when($min !== null, fn ($query) => $query->where('total_amount', '>=', $min))
            ->when($max !== null, fn ($query) => $query->where('total_amount', '<=', $max));
    }

    /**
     * خلاصه آماری نتایج فیلترشده
     */
    #[\Livewire\Attributes\Computed]
    public function summary(): array
    {
        $row = $this->filteredQuery()
            ->toBase()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_sum')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_sum")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'unpaid' THEN total_amount ELSE 0 END), 0) as unpaid_sum")
            ->first();

        return [
            'count' => (int) ($row->total_count ?? 0),
            'sum' => (int) ($row->total_sum ?? 0),
            'paid' => (int) ($row->paid_sum ?? 0),
            'unpaid' => (int) ($row->unpaid_sum ?? 0),
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function invoices()
    {
        $query = $this->filteredQuery()->with([
            'user:id,first_name,last_name,mobile',
            'order:id,order_number,payment_method',
        ]);

        match ($this->sort) {
            'oldest' => $query->oldest('id'),
            'amount_desc' => $query->orderByDesc('total_amount')->latest('id'),
            'amount_asc' => $query->orderBy('total_amount')->latest('id'),
            default => $query->latest('id'),
        };

        return $query->paginate(in_array($this->perPage, [15, 30, 50, 100], true) ? $this->perPage : 15);
    }

    public function showInvoice(int $id): void
    {
        abort_unless(auth()->user()?->can('invoices.view'), 403);

        $this->selectedInvoice = $this->model
            ->newQuery()
            ->with([
                'user:id,first_name,last_name,mobile,email',
                'order:id,user_id,order_number,address_id,shipping_slot_id,delivery_date,status,payment_status,payment_method,subtotal,discount_amount,tax_amount,shipping_amount,total_amount,expires_at,created_at,updated_at',
                'order.address',
                'order.payments',
                'order.shipment',
                'order.shippingSlot',
                'items.inventory:id,title',
                'stockMovements.inventory:id,title',
            ])
            ->findOrFail($id);
    }

    // انتقال‌های مجاز وضعیت هر فاکتور (هماهنگ با موجودی انبار)
    public function allowedStatuses(Invoice $invoice): array
    {
        return array_intersect_key(
            $this->statuses(),
            array_flip(app(\App\Services\Invoices\InvoiceStockService::class)->allowedTransitions($invoice))
        );
    }

    public function isEditable(Invoice $invoice): bool
    {
        return app(\App\Services\Invoices\InvoiceStockService::class)->isEditable($invoice);
    }

    public function deleteInvoice(int $id): void
    {
        abort_unless(auth()->user()?->can('invoices.delete'), 403);

        try {
            app(\App\Services\Invoices\InvoiceStockService::class)->delete($id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: collect($e->errors())->flatten()->first());
            return;
        }

        if ($this->selectedInvoice?->id === $id) {
            $this->selectedInvoice = null;
            $this->dispatch('close-modal');
        }

        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'فاکتور حذف شد و موجودی کالاها به انبار بازگشت.');
    }

    public function closeInvoice(): void
    {
        $this->selectedInvoice = null;
    }

    public function updateStatus(int $id, string $status): void
    {
        abort_unless(auth()->user()?->can('invoices.edit'), 403);

        if (!array_key_exists($status, $this->statuses())) {
            return;
        }

        // تغییر وضعیت + همگام‌سازی موجودی انبار (idempotent، فقط اختلاف اعمال می‌شود)
        try {
            $invoice = app(\App\Services\Invoices\InvoiceStockService::class)->changeStatus($id, $status);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: collect($e->errors())->flatten()->first());
            return;
        }

        if ($this->selectedInvoice?->id === $invoice->id) {
            $this->showInvoice($invoice->id);
        }

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'وضعیت فاکتور با موفقیت به‌روزرسانی شد.'
        );
    }

    public function statusLabel(?string $status): string
    {
        return $this->statuses()[$status ?? ''] ?? 'نامشخص';
    }

    public function statusBadge(?string $status): string
    {
        return match ($status) {
            'draft'     => 'bg-outline-secondary',
            'unpaid'    => 'bg-outline-warning',
            'paid'      => 'bg-outline-success',
            'cancelled' => 'bg-outline-dark',
            'refunded'  => 'bg-outline-danger',
            default     => 'bg-outline-secondary',
        };
    }

    public function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'wallet'      => 'کیف‌پول',
            'gateway'     => 'درگاه پرداخت',
            'installment' => 'اقساطی',
            'cod'         => 'پرداخت در محل',
            'transfer'    => 'کارت به کارت',
            default       => $method ?: '—',
        };
    }

    public function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'در انتظار',
            'success' => 'موفق',
            'failed'  => 'ناموفق',
            default   => $status ?: '—',
        };
    }

    public function paymentStatusBadge(?string $status): string
    {
        return match ($status) {
            'success' => 'bg-outline-success',
            'failed'  => 'bg-outline-danger',
            'pending' => 'bg-outline-warning',
            default   => 'bg-outline-secondary',
        };
    }

    public function shipmentMethodLabel(?string $method): string
    {
        return match ($method) {
            'post'    => 'پست',
            'courier' => 'پیک',
            'pickup'  => 'تحویل حضوری',
            default   => $method ?: '—',
        };
    }

    public function shipmentStatusLabel(?string $status): string
    {
        return match ($status) {
            'waiting'   => 'در انتظار ارسال',
            'sent'      => 'ارسال شده',
            'delivered' => 'تحویل داده شده',
            default     => $status ?: '—',
        };
    }

    public function orderStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending'    => 'در انتظار بررسی',
            'processing' => 'در حال پردازش',
            'shipped'    => 'ارسال شده',
            'completed'  => 'تکمیل شده',
            'cancelled'  => 'لغو شده',
            default      => $status ?: '—',
        };
    }

    // اصلاح: قبلاً وضعیت پرداخت سفارش با یک match تکراری مستقیم داخل blade نوشته شده بود.
    // به یک متد اختصاصی منتقل شد تا هم با بقیه‌ی متدهای Label یکدست باشد و هم بج رنگی داشته باشد.
    public function orderPaymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'unpaid'   => 'پرداخت نشده',
            'pending'  => 'در انتظار پرداخت',
            'paid'     => 'پرداخت شده',
            'failed'   => 'پرداخت ناموفق',
            'refunded' => 'بازپرداخت شده',
            default    => '—',
        };
    }

    public function orderPaymentStatusBadge(?string $status): string
    {
        return match ($status) {
            'paid'     => 'bg-outline-success',
            'failed'   => 'bg-outline-danger',
            'refunded' => 'bg-outline-danger',
            'pending'  => 'bg-outline-warning',
            'unpaid'   => 'bg-outline-secondary',
            default    => 'bg-outline-secondary',
        };
    }

    // اصلاح: تابع کمکی برای فرمت مبلغ به تومان، به‌جای تکرار number_format(...) . ' تومان'
    // در چند جای مختلف view، و محافظت در برابر مقدار null.
    public function money(int|string|null $amount): string
    {
        return number_format((int) ($amount ?? 0)) . ' تومان';
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">لیست فاکتورها</h1>
            <div class="text-muted small">مدیریت، بررسی و پیگیری فاکتورهای ثبت‌شده</div>
        </div>
        @can('invoices.create')
            <div class="btn-list">
                <a href="{{ route('invoices.create') }}" class="btn btn-success-light btn-wave">
                    <i class="ri-add-line align-middle"></i>
                    صدور فاکتور / فروش حضوری
                </a>
            </div>
        @endcan
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header d-block pb-3">

                    {{-- خلاصه نتایج فیلترشده --}}
                    @php
                        $summary = $this->summary;
                    @endphp
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-lg-3">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">تعداد فاکتور</div>
                                <div class="fw-bold fs-15">{{ number_format($summary['count']) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">جمع مبالغ</div>
                                <div class="fw-bold fs-15">{{ $this->money($summary['sum']) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">پرداخت‌شده</div>
                                <div class="fw-bold fs-15 text-success">{{ $this->money($summary['paid']) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">پرداخت‌نشده</div>
                                <div class="fw-bold fs-15 text-warning">{{ $this->money($summary['unpaid']) }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- فیلترهای اصلی --}}
                    <div class="row g-2 align-items-end">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label small text-muted mb-1">جستجو</label>
                            <input
                                wire:model.live.debounce.400ms="search"
                                type="search"
                                autocomplete="off"
                                class="form-control form-control-sm"
                                placeholder="شماره فاکتور، سفارش، نام یا موبایل مشتری..."
                            >
                        </div>

                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small text-muted mb-1">وضعیت</label>
                            <select wire:model.live="status" class="form-select form-select-sm">
                                <option value="">همه وضعیت‌ها</option>
                                @foreach($this->statuses as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small text-muted mb-1">نوع فاکتور</label>
                            <select wire:model.live="source" class="form-select form-select-sm">
                                <option value="">همه</option>
                                @foreach($this->sources as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small text-muted mb-1">مرتب‌سازی</label>
                            <select wire:model.live="sort" class="form-select form-select-sm">
                                @foreach($this->sorts as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-3 col-6 d-flex gap-2">
                            <button type="button"
                                    wire:click="$toggle('showAdvanced')"
                                    class="btn btn-sm {{ $showAdvanced ? 'btn-primary' : 'btn-primary-light' }} flex-fill">
                                <i class="ri-filter-3-line align-middle"></i>
                                پیشرفته
                            </button>
                            <button
                                wire:click="clearFilters"
                                type="button"
                                class="btn btn-light btn-sm"
                                title="پاک کردن همه فیلترها"
                                @if(!$this->hasActiveFilters) disabled @endif
                            >
                                <i class="ri-refresh-line align-middle"></i>
                            </button>
                        </div>
                    </div>

                    {{-- فیلترهای پیشرفته --}}
                    @if($showAdvanced)
                        <div class="row g-2 align-items-end mt-1 pt-2 border-top">

                            {{-- کاربر --}}
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label small text-muted mb-1">کاربر</label>
                                @if($this->filterUser)
                                    <div class="d-flex align-items-center justify-content-between border rounded px-2 py-1">
                                        <span class="small">
                                            <i class="ri-user-line me-1"></i>
                                            {{ $this->filterUser->mobile }}
                                            {{ trim($this->filterUser->full_name) ? ' - ' . $this->filterUser->full_name : '' }}
                                        </span>
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0" wire:click="clearFilter('user')" title="حذف فیلتر کاربر">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                @else
                                    <div class="position-relative">
                                        <input type="search"
                                               wire:model.live.debounce.400ms="userSearch"
                                               autocomplete="off"
                                               class="form-control form-control-sm"
                                               placeholder="نام یا موبایل کاربر...">

                                        @if($this->userResults->isNotEmpty())
                                            <div class="list-group position-absolute w-100 shadow mt-1" style="z-index: 30">
                                                @foreach($this->userResults as $u)
                                                    <button type="button"
                                                            wire:key="filter-user-{{ $u->id }}"
                                                            wire:click="filterByUser({{ $u->id }})"
                                                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center small">
                                                        <span>{{ $u->mobile }} {{ trim($u->full_name) ? ' - ' . $u->full_name : '' }}</span>
                                                        <span class="badge bg-light text-dark">{{ $u->invoices_count }} فاکتور</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @elseif(mb_strlen(trim($userSearch)) >= 2)
                                            <div class="list-group position-absolute w-100 shadow mt-1" style="z-index: 30">
                                                <div class="list-group-item small text-muted">کاربری پیدا نشد.</div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="col-lg-2 col-md-3 col-6">
                                <label class="form-label small text-muted mb-1">روش پرداخت</label>
                                <select wire:model.live="paymentMethod" class="form-select form-select-sm">
                                    <option value="">همه</option>
                                    @foreach($this->paymentMethods as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <label class="form-label small text-muted mb-1">بازه تاریخ (شمسی)</label>
                                <div class="input-group input-group-sm">
                                    <input wire:model.live.debounce.600ms="fromDate" type="text" dir="ltr" class="form-control text-center" placeholder="از ۱۴۰۵/۰۱/۰۱">
                                    <input wire:model.live.debounce.600ms="toDate" type="text" dir="ltr" class="form-control text-center" placeholder="تا ۱۴۰۵/۱۲/۲۹">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <label class="form-label small text-muted mb-1">بازه مبلغ (تومان)</label>
                                <div class="input-group input-group-sm">
                                    <input wire:model.live.debounce.600ms="minAmount" type="text" inputmode="numeric" dir="ltr" class="form-control text-center" placeholder="حداقل">
                                    <input wire:model.live.debounce.600ms="maxAmount" type="text" inputmode="numeric" dir="ltr" class="form-control text-center" placeholder="حداکثر">
                                </div>
                            </div>

                            @if($this->dateError)
                                <div class="col-12 small text-danger">{{ $this->dateError }}</div>
                            @endif
                        </div>
                    @endif

                    {{-- فیلترهای فعال --}}
                    @if($this->hasActiveFilters)
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                            <span class="small text-muted">فیلترهای فعال:</span>

                            @if(trim($search) !== '')
                                <span class="badge bg-primary-transparent">جستجو: {{ $search }} <i class="ri-close-line ms-1" role="button" wire:click="clearFilter('search')"></i></span>
                            @endif
                            @if($status !== '')
                                <span class="badge bg-primary-transparent">وضعیت: {{ $this->statuses[$status] ?? $status }} <i class="ri-close-line ms-1" role="button" wire:click="clearFilter('status')"></i></span>
                            @endif
                            @if($source !== '')
                                <span class="badge bg-primary-transparent">نوع: {{ $this->sources[$source] ?? $source }} <i class="ri-close-line ms-1" role="button" wire:click="clearFilter('source')"></i></span>
                            @endif
                            @if($this->filterUser)
                                <span class="badge bg-primary-transparent">کاربر: {{ $this->filterUser->mobile }} <i class="ri-close-line ms-1" role="button" wire:click="clearFilter('user')"></i></span>
                            @endif
                            @if($paymentMethod !== '')
                                <span class="badge bg-primary-transparent">پرداخت: {{ $this->paymentMethods[$paymentMethod] ?? $paymentMethod }} <i class="ri-close-line ms-1" role="button" wire:click="clearFilter('paymentMethod')"></i></span>
                            @endif
                            @if(filled($fromDate) || filled($toDate))
                                <span class="badge bg-primary-transparent">تاریخ: {{ $fromDate ?: '...' }} تا {{ $toDate ?: '...' }} <i class="ri-close-line ms-1" role="button" wire:click="clearFilter('date')"></i></span>
                            @endif
                            @if(filled($minAmount) || filled($maxAmount))
                                <span class="badge bg-primary-transparent">مبلغ: {{ filled($minAmount) ? $minAmount : '۰' }} تا {{ filled($maxAmount) ? $maxAmount : '∞' }} <i class="ri-close-line ms-1" role="button" wire:click="clearFilter('amount')"></i></span>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover text-nowrap align-middle mb-0">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>شماره فاکتور</th>
                                <th>شماره سفارش</th>
                                <th>مشتری</th>
                                <th>مبلغ نهایی</th>
                                <th>وضعیت</th>
                                <th>تاریخ ثبت</th>
                                <th class="text-center">عملیات</th>
                            </tr>
                            </thead>

                            <tbody
                                wire:loading.class="opacity-50"
                                wire:target="search,status,source,paymentMethod,userId,filterByUser,fromDate,toDate,minAmount,maxAmount,sort,perPage,clearFilters,clearFilter"
                            >
                            @php
                                $invoices = $this->invoices;
                                $counter = $invoices->firstItem() ?? 1;
                            @endphp

                            @forelse($invoices as $invoice)
                                @php
                                    $customerName = trim(
                                        ($invoice->user?->first_name ?? '') . ' ' .
                                        ($invoice->user?->last_name ?? '')
                                    );
                                @endphp

                                <tr wire:key="invoice-row-{{ $invoice->id }}">
                                    <td class="text-muted">{{ $counter++ }}</td>

                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-link p-0 text-primary fw-medium text-decoration-none"
                                            data-bs-toggle="modal"
                                            data-bs-target="#invoiceDetailModal"
                                            wire:click="showInvoice({{ $invoice->id }})"
                                        >
                                            {{ $invoice->invoice_number }}
                                        </button>
                                        @if($invoice->source !== 'online')
                                            <div><span class="badge bg-secondary-transparent mt-1">{{ \App\Models\Invoice::SOURCES[$invoice->source] ?? $invoice->source }}</span></div>
                                        @endif
                                    </td>

                                    <td>
                                        @if($invoice->order?->order_number)
                                            <span class="text-muted">
                        {{ $invoice->order->order_number }}
                    </span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td>
                                        <div class="fw-medium">
                                            @if($invoice->user_id)
                                                <button type="button"
                                                        class="btn btn-link p-0 text-reset fw-medium text-decoration-none"
                                                        wire:click="filterByUser({{ $invoice->user_id }})"
                                                        title="نمایش همه فاکتورهای این کاربر">
                                                    {{ $customerName !== '' ? $customerName : ($invoice->customer_name ?: 'مشتری بدون نام') }}
                                                    <i class="ri-filter-3-line text-muted small"></i>
                                                </button>
                                            @else
                                                {{ $customerName !== '' ? $customerName : ($invoice->customer_name ?: 'مشتری بدون نام') }}
                                            @endif
                                        </div>

                                        @if($invoice->user?->mobile || $invoice->customer_mobile)
                                            <div class="small text-muted mt-1">
                                                {{ $invoice->user?->mobile ?? $invoice->customer_mobile }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="fw-medium">
                                        {{ $this->money($invoice->total_amount) }}
                                    </td>

                                    <td>
                                        @if(auth()->user()?->can('invoices.edit') && $this->allowedStatuses($invoice))
                                            <div class="dropdown">
                                                <button
                                                    type="button"
                                                    class="badge {{ $this->statusBadge($invoice->status) }} border-0 dropdown-toggle"
                                                    data-bs-toggle="dropdown"
                                                >
                                                    {{ $this->statusLabel($invoice->status) }}
                                                </button>

                                                <ul class="dropdown-menu">
                                                    @foreach($this->allowedStatuses($invoice) as $key => $label)
                                                        <li>
                                                            <button
                                                                type="button"
                                                                class="dropdown-item {{ $invoice->status === $key ? 'active' : '' }}"
                                                                wire:click="updateStatus({{ $invoice->id }}, '{{ $key }}')"
                                                            >
                                                                {{ $label }}
                                                            </button>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @else
                                            <span class="badge {{ $this->statusBadge($invoice->status) }} border-0">
                        {{ $this->statusLabel($invoice->status) }}
                    </span>
                                        @endif
                                    </td>

                                    <td class="text-muted small">
                                        {{ $invoice->created_at?->translatedFormat('Y/m/d H:i') ?? '—' }}
                                    </td>

                                    <td>
                                        <div class="hstack justify-content-center gap-3">
                                            <button
                                                type="button"
                                                class="btn btn-link p-0 text-info"
                                                title="مشاهده جزئیات"
                                                data-bs-toggle="modal"
                                                data-bs-target="#invoiceDetailModal"
                                                wire:click="showInvoice({{ $invoice->id }})"
                                            >
                                                <i class="ri-eye-line fs-16"></i>
                                            </button>

                                            @can('invoices.edit')
                                                @if($this->isEditable($invoice))
                                                    <a href="{{ route('invoices.edit', $invoice->id) }}" class="text-warning" title="ویرایش فاکتور">
                                                        <i class="ri-edit-line fs-16"></i>
                                                    </a>
                                                @endif
                                            @endcan

                                            @can('invoices.delete')
                                                @if($invoice->source !== 'online' && !$invoice->order_id)
                                                    <button type="button"
                                                            class="btn btn-link p-0 text-danger"
                                                            title="حذف فاکتور"
                                                            wire:click="deleteInvoice({{ $invoice->id }})"
                                                            wire:confirm="فاکتور حذف و موجودی کسرشده به انبار بازگردانده می‌شود. ادامه می‌دهید؟">
                                                        <i class="ri-delete-bin-5-line fs-16"></i>
                                                    </button>
                                                @endif
                                            @endcan

                                            <a
{{--                                                href="{{ route('admin.invoices.print', $invoice->id) }}"--}}
                                                target="_blank"
                                                rel="noopener"
                                                class="text-secondary"
                                                title="چاپ فاکتور"
                                            >
                                                <i class="ri-printer-line fs-16"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="ri-file-list-3-line fs-1 d-block mb-2"></i>

                                        <div class="fw-medium">
                                            فاکتوری برای نمایش وجود ندارد.
                                        </div>

                                        @if($this->hasActiveFilters)
                                            <div class="small mt-1">
                                                فیلترها را تغییر دهید یا پاک کنید.
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                        </table>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
                        <div class="d-flex align-items-center gap-2 small text-muted">
                            <span>نمایش</span>
                            <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 80px">
                                @foreach([15, 30, 50, 100] as $size)
                                    <option value="{{ $size }}">{{ $size }}</option>
                                @endforeach
                            </select>
                            <span>
                                مورد در هر صفحه
                                @if($invoices->total())
                                    — {{ number_format($invoices->firstItem()) }} تا {{ number_format($invoices->lastItem()) }} از {{ number_format($invoices->total()) }}
                                @endif
                            </span>
                        </div>

                        @if($invoices->hasPages())
                            <div>
                                {{ $invoices->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="invoiceDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        جزئیات فاکتور
                        @if($selectedInvoice)
                            <span class="text-muted small">{{ $selectedInvoice->invoice_number }}</span>
                        @endif
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        wire:click="closeInvoice"
                    ></button>
                </div>

                <div class="modal-body text-start">
                    @if($selectedInvoice)
                        <div wire:loading.class="opacity-50" wire:target="showInvoice,updateStatus">
                            <div class="row g-3 mb-4">
                                <div class="col-sm-6 col-lg-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">شماره فاکتور</div>
                                        <div class="fw-semibold">{{ $selectedInvoice->invoice_number }}</div>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">شماره سفارش</div>
                                        <div class="fw-semibold">{{ $selectedInvoice->order?->order_number ?? '—' }}</div>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">وضعیت فاکتور</div>

                                        {{-- اصلاح: امکان تغییر وضعیت از داخل مودال هم اضافه شد --}}
                                        @if(auth()->user()?->can('invoices.edit') && $this->allowedStatuses($selectedInvoice))
                                            <div class="dropdown">
                                                <button
                                                    type="button"
                                                    class="badge {{ $this->statusBadge($selectedInvoice->status) }} border-0 dropdown-toggle"
                                                    data-bs-toggle="dropdown"
                                                >
                                                    {{ $this->statusLabel($selectedInvoice->status) }}
                                                </button>
                                                <ul class="dropdown-menu">
                                                    @foreach($this->allowedStatuses($selectedInvoice) as $key => $label)
                                                        <li>
                                                            <button
                                                                type="button"
                                                                class="dropdown-item {{ $selectedInvoice->status === $key ? 'active' : '' }}"
                                                                wire:click="updateStatus({{ $selectedInvoice->id }}, '{{ $key }}')"
                                                            >
                                                                {{ $label }}
                                                            </button>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @else
                                            <span class="badge {{ $this->statusBadge($selectedInvoice->status) }}">
                                                {{ $this->statusLabel($selectedInvoice->status) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">تاریخ ثبت</div>
                                        <div class="fw-semibold">
                                            {{ $selectedInvoice->created_at?->translatedFormat('Y/m/d H:i') ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-lg-6">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-3">
                                                <i class="ri-user-line me-1"></i>
                                                اطلاعات مشتری
                                            </h6>

                                            @php
                                                $selectedCustomerName = trim(
                                                    ($selectedInvoice->user?->first_name ?? '') . ' ' .
                                                    ($selectedInvoice->user?->last_name ?? '')
                                                );
                                            @endphp

                                            <div class="small mb-2">
                                                <span class="text-muted">نام:</span>
                                                {{ $selectedCustomerName !== '' ? $selectedCustomerName : '—' }}
                                            </div>
                                            <div class="small mb-2">
                                                <span class="text-muted">موبایل:</span>
                                                {{ $selectedInvoice->user?->mobile ?? '—' }}
                                            </div>
                                            <div class="small">
                                                <span class="text-muted">ایمیل:</span>
                                                {{ $selectedInvoice->user?->email ?? '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-3">
                                                <i class="ri-map-pin-line me-1"></i>
                                                آدرس ارسال
                                            </h6>

                                            @if($selectedInvoice->order?->address)
                                                <div class="small fw-medium mb-2">
                                                    {{ $selectedInvoice->order->address->receiver_name }}
                                                    <span class="text-muted">({{ $selectedInvoice->order->address->phone }})</span>
                                                </div>
                                                <div class="small text-muted">
                                                    {{ $selectedInvoice->order->address->province }}،
                                                    {{ $selectedInvoice->order->address->city }}،
                                                    {{ $selectedInvoice->order->address->address }}
                                                </div>
                                                @if($selectedInvoice->order->address->postal_code)
                                                    <div class="small text-muted mt-2">
                                                        کد پستی: {{ $selectedInvoice->order->address->postal_code }}
                                                    </div>
                                                @endif
                                            @else
                                                <div class="small text-muted">آدرس ارسال برای این سفارش ثبت نشده است.</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border shadow-none mb-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h6 class="fw-bold mb-0">
                                            <i class="ri-shopping-bag-3-line me-1"></i>
                                            اقلام فاکتور
                                        </h6>
                                        <span class="text-muted small">
                                            {{ $selectedInvoice->items->count() }} قلم
                                        </span>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle text-nowrap mb-0">
                                            <thead>
                                            <tr>
                                                <th>محصول</th>
                                                <th>تنوع</th>
                                                <th>تعداد</th>
                                                <th>انبار</th>
                                                <th>وضعیت موجودی</th>
                                                <th>قیمت واحد</th>
                                                <th>قیمت کل</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @forelse($selectedInvoice->items as $item)
                                                <tr wire:key="invoice-item-{{ $item->id }}">
                                                    <td class="fw-medium">{{ $item->product_name }}</td>
                                                    <td class="text-muted">{{ $item->variant_name ?: '—' }}</td>
                                                    <td>{{ number_format((int) $item->quantity) }}</td>
                                                    <td class="text-muted">{{ $item->inventory?->title ?? '—' }}</td>
                                                    <td>
                                                        @if($item->stock_deducted)
                                                            <span class="badge bg-success-transparent">کسر شده: {{ $item->stock_deducted }}</span>
                                                        @endif
                                                        @if($item->stock_reserved)
                                                            <span class="badge bg-warning-transparent">رزرو: {{ $item->stock_reserved }}</span>
                                                        @endif
                                                        @if(!$item->stock_deducted && !$item->stock_reserved)
                                                            <span class="text-muted small">بدون اثر</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $this->money($item->unit_price) }}</td>
                                                    <td class="fw-medium">{{ $this->money($item->total_price) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-4">
                                                        قلمی برای این فاکتور ثبت نشده است.
                                                    </td>
                                                </tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            @if($selectedInvoice->stockMovements->isNotEmpty())
                                <div class="card border shadow-none mb-4">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">
                                            <i class="ri-history-line me-1"></i>
                                            گردش موجودی این فاکتور
                                        </h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle text-nowrap mb-0">
                                                <thead>
                                                <tr>
                                                    <th>تاریخ</th>
                                                    <th>نوع</th>
                                                    <th>انبار</th>
                                                    <th>تغییر موجودی</th>
                                                    <th>تغییر رزرو</th>
                                                    <th>موجودی / رزرو بعد</th>
                                                    <th>توضیح</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @foreach($selectedInvoice->stockMovements as $movement)
                                                    <tr wire:key="movement-{{ $movement->id }}">
                                                        <td class="small">{{ verta($movement->created_at)->format('Y/m/d H:i') }}</td>
                                                        <td>{{ $movement->type_label }}</td>
                                                        <td>{{ $movement->inventory?->title }}</td>
                                                        <td class="{{ $movement->quantity_change < 0 ? 'text-danger' : ($movement->quantity_change > 0 ? 'text-success' : 'text-muted') }}" dir="ltr">{{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}</td>
                                                        <td class="text-muted" dir="ltr">{{ $movement->reserved_change > 0 ? '+' : '' }}{{ $movement->reserved_change }}</td>
                                                        <td>{{ $movement->quantity_after }} / {{ $movement->reserved_after }}</td>
                                                        <td class="small text-muted">{{ $movement->note }}</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="row g-3 mb-4">
                                <div class="col-lg-7">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-3">
                                                <i class="ri-shopping-cart-2-line me-1"></i>
                                                وضعیت سفارش
                                            </h6>

                                            <div class="row g-3 small">
                                                <div class="col-md-6">
                                                    <span class="text-muted d-block mb-1">وضعیت سفارش</span>
                                                    <span class="fw-medium">
                                                        {{ $this->orderStatusLabel($selectedInvoice->order?->status) }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    <span class="text-muted d-block mb-1">وضعیت پرداخت</span>
                                                    <span class="badge {{ $this->orderPaymentStatusBadge($selectedInvoice->order?->payment_status) }}">
                                                        {{ $this->orderPaymentStatusLabel($selectedInvoice->order?->payment_status) }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    <span class="text-muted d-block mb-1">روش پرداخت</span>
                                                    <span class="fw-medium">
                                                        {{ $this->paymentMethodLabel($selectedInvoice->order?->payment_method) }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    <span class="text-muted d-block mb-1">تاریخ ثبت سفارش</span>
                                                    <span class="fw-medium">
                                                        {{ $selectedInvoice->order?->created_at?->translatedFormat('Y/m/d H:i') ?? '—' }}
                                                    </span>
                                                </div>

                                                {{-- اصلاح: اطلاعات بازه‌ی ارسال (shipping_slot) لود می‌شد ولی هیچ‌جا نمایش داده نمی‌شد --}}
                                                <div class="col-md-6">
                                                    <span class="text-muted d-block mb-1">تاریخ ارسال انتخابی</span>
                                                    <span class="fw-medium">
                                                        @if($selectedInvoice->order?->delivery_date)
                                                            {{ verta($selectedInvoice->order->delivery_date)->format('l j F Y') }}
                                                            @if($selectedInvoice->order->shippingSlot?->label)
                                                                <span class="badge bg-light text-dark ms-1">{{ $selectedInvoice->order->shippingSlot->label }}</span>
                                                            @endif
                                                            @if((int) $selectedInvoice->order->shipping_amount > 0)
                                                                <span class="text-muted small">({{ $this->money($selectedInvoice->order->shipping_amount) }})</span>
                                                            @else
                                                                <span class="text-success small">(ارسال رایگان)</span>
                                                            @endif
                                                        @elseif($selectedInvoice->order?->shippingSlot)
                                                            {{-- سفارش‌های قدیمی (قبل از زمان‌بندی خودکار) --}}
                                                            {{ $selectedInvoice->order->shippingSlot->label
                                                                ?? verta($selectedInvoice->order->shippingSlot->date)->format('Y/m/d') }}
                                                        @else
                                                            —
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    <span class="text-muted d-block mb-1">مهلت پرداخت</span>
                                                    <span class="fw-medium">
                                                        {{ $selectedInvoice->order?->expires_at?->translatedFormat('Y/m/d H:i') ?? '—' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-5">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-3">جمع‌بندی مبلغ</h6>

                                            <div class="d-flex justify-content-between small mb-2">
                                                <span class="text-muted">جمع جزء</span>
                                                <span>{{ $this->money($selectedInvoice->subtotal) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between small mb-2">
                                                <span class="text-muted">تخفیف</span>
                                                <span class="text-danger">- {{ $this->money($selectedInvoice->discount_amount) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between small mb-2">
                                                <span class="text-muted">مالیات</span>
                                                <span>{{ $this->money($selectedInvoice->tax_amount) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between small mb-3">
                                                <span class="text-muted">هزینه ارسال</span>
                                                <span>{{ $this->money($selectedInvoice->shipping_amount) }}</span>
                                            </div>

                                            <hr>

                                            <div class="d-flex justify-content-between fw-bold">
                                                <span>مبلغ نهایی</span>
                                                <span>{{ $this->money($selectedInvoice->total_amount) }}</span>
                                            </div>

                                            @if($selectedInvoice->paid_at)
                                                <div class="text-success small mt-3">
                                                    <i class="ri-checkbox-circle-line"></i>
                                                    پرداخت در
                                                    {{ $selectedInvoice->paid_at->translatedFormat('Y/m/d H:i') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border shadow-none mb-4">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3">
                                        <i class="ri-bank-card-line me-1"></i>
                                        تراکنش‌های پرداخت
                                    </h6>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle text-nowrap mb-0">
                                            <thead>
                                            <tr>
                                                <th>روش</th>
                                                <th>درگاه</th>
                                                <th>کد تراکنش</th>
                                                <th>مبلغ</th>
                                                <th>وضعیت</th>
                                                <th>تاریخ پرداخت</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @forelse($selectedInvoice->order?->payments ?? [] as $payment)
                                                <tr wire:key="payment-{{ $payment->id }}">
                                                    <td>{{ $this->paymentMethodLabel($payment->method) }}</td>
                                                    <td>{{ $payment->gateway ?: '—' }}</td>
                                                    <td class="small text-muted">{{ $payment->transaction_id ?: '—' }}</td>
                                                    <td>{{ $this->money($payment->amount) }}</td>
                                                    <td>
                                                        <span class="badge {{ $this->paymentStatusBadge($payment->status) }}">
                                                            {{ $this->paymentStatusLabel($payment->status) }}
                                                        </span>
                                                    </td>
                                                    <td class="small text-muted">
                                                        {{ $payment->paid_at?->translatedFormat('Y/m/d H:i') ?? '—' }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        هیچ تراکنشی برای این سفارش ثبت نشده است.
                                                    </td>
                                                </tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="card border shadow-none">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3">
                                        <i class="ri-truck-line me-1"></i>
                                        اطلاعات ارسال
                                    </h6>

                                    @if($selectedInvoice->order?->shipment)
                                        <div class="row g-3 small">
                                            <div class="col-md-3">
                                                <span class="text-muted d-block mb-1">روش ارسال</span>
                                                <span class="fw-medium">
                                                    {{ $this->shipmentMethodLabel($selectedInvoice->order->shipment->method) }}
                                                </span>
                                            </div>
                                            <div class="col-md-3">
                                                <span class="text-muted d-block mb-1">حامل</span>
                                                <span class="fw-medium">{{ $selectedInvoice->order->shipment->carrier ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-3">
                                                <span class="text-muted d-block mb-1">کد رهگیری</span>
                                                <span class="fw-medium">{{ $selectedInvoice->order->shipment->tracking_code ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-3">
                                                <span class="text-muted d-block mb-1">وضعیت</span>
                                                <span class="badge bg-outline-primary">
                                                    {{ $this->shipmentStatusLabel($selectedInvoice->order->shipment->status) }}
                                                </span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="small text-muted">اطلاعات ارسال برای این سفارش ثبت نشده است.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div wire:loading wire:target="showInvoice" class="spinner-border text-info" role="status"></div>
                            <div wire:loading.remove wire:target="showInvoice" class="text-muted">
                                فاکتوری انتخاب نشده است.
                            </div>
                        </div>
                    @endif
                </div>

                <div class="modal-footer">
                    @if($selectedInvoice)
                        <a
{{--                            href="{{ route('admin.invoices.print', $selectedInvoice->id) }}"--}}
                            target="_blank"
                            rel="noopener"
                            class="btn btn-info-light"
                        >
                            <i class="ri-printer-line align-middle"></i>
                            چاپ فاکتور
                        </a>
                    @endif

                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                        wire:click="closeInvoice"
                    >
                        بستن
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
