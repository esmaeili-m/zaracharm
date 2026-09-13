<?php

use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Invoice $model;

    public ?Invoice $selectedInvoice = null;

    public string $search = '';
    public string $status = '';
    public ?string $fromDate = null;
    public ?string $toDate = null;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Invoice $model): void
    {
        abort_unless(auth()->user()?->can('invoices.view'), 403);

        $this->model = $model;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFromDate(): void
    {
        $this->resetPage();
    }

    public function updatingToDate(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'fromDate', 'toDate']);
        $this->resetPage();
    }

    #[\Livewire\Attributes\Computed]
    public function statuses(): array
    {
        return [
            'draft'     => 'پیش‌نویس',
            'unpaid'    => 'پرداخت‌نشده',
            'paid'      => 'پرداخت‌شده',
            'cancelled' => 'لغو شده',
            'refunded'  => 'بازگشت وجه',
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function hasActiveFilters(): bool
    {
        return trim($this->search) !== '' || $this->status !== '' || $this->fromDate || $this->toDate;
    }

    #[\Livewire\Attributes\Computed]
    public function invoices()
    {
        return $this->model
            ->newQuery()
            ->with([
                'user:id,first_name,last_name,mobile',
                'order:id,order_number',
            ])
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
                        });
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->fromDate, fn ($query) => $query->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($query) => $query->whereDate('created_at', '<=', $this->toDate))
            ->latest('id')
            ->paginate(15);
    }

    public function showInvoice(int $id): void
    {
        abort_unless(auth()->user()?->can('invoices.view'), 403);

        $this->selectedInvoice = $this->model
            ->newQuery()
            ->with([
                'user:id,first_name,last_name,mobile,email',
                'order:id,user_id,order_number,address_id,shipping_slot_id,status,payment_status,payment_method,subtotal,discount_amount,tax_amount,shipping_amount,total_amount,expires_at,created_at,updated_at',
                'order.address',
                'order.payments',
                'order.shipment',
                'order.shippingSlot',
                'items',
            ])
            ->findOrFail($id);
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

        $invoice = $this->model->newQuery()->findOrFail($id);

        $invoice->update([
            'status' => $status,
            'paid_at' => $status === 'paid'
                ? ($invoice->paid_at ?? now())
                : $invoice->paid_at,
        ]);

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
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header pb-0 justify-content-between flex-wrap gap-3">
                    <div class="card-title">فاکتورها</div>

                    <div class="d-flex flex-wrap align-items-center gap-2 my-auto">
                        <select wire:model.live="status" class="form-select form-select-sm" style="min-width: 150px;">
                            <option value="">همه وضعیت‌ها</option>
                            @foreach($this->statuses() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>

                        <input
                            wire:model.live="fromDate"
                            type="date"
                            class="form-control form-control-sm"
                            title="از تاریخ"
                        >

                        <input
                            wire:model.live="toDate"
                            type="date"
                            class="form-control form-control-sm"
                            title="تا تاریخ"
                        >

                        <input
                            wire:model.live.debounce.400ms="search"
                            type="search"
                            autocomplete="off"
                            class="form-control form-control-sm"
                            style="min-width: 260px;"
                            placeholder="شماره فاکتور، سفارش یا مشتری..."
                        >

                        <button
                            wire:click="clearFilters"
                            type="button"
                            class="btn btn-light-brand btn-sm"
                            @if(!$this->hasActiveFilters) disabled @endif
                        >
                            <i class="ri-refresh-line align-middle"></i>
                            پاک کردن
                        </button>
                    </div>
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
                                wire:target="search,status,fromDate,toDate,clearFilters"
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
                                            {{ $customerName !== '' ? $customerName : 'مشتری بدون نام' }}
                                        </div>

                                        @if($invoice->user?->mobile)
                                            <div class="small text-muted mt-1">
                                                {{ $invoice->user->mobile }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="fw-medium">
                                        {{ $this->money($invoice->total_amount) }}
                                    </td>

                                    <td>
                                        @if(auth()->user()?->can('invoices.edit'))
                                            <div class="dropdown">
                                                <button
                                                    type="button"
                                                    class="badge {{ $this->statusBadge($invoice->status) }} border-0 dropdown-toggle"
                                                    data-bs-toggle="dropdown"
                                                >
                                                    {{ $this->statusLabel($invoice->status) }}
                                                </button>

                                                <ul class="dropdown-menu">
                                                    @foreach($this->statuses() as $key => $label)
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

                    @if($invoices->hasPages())
                        <div class="mt-3">
                            {{ $invoices->links() }}
                        </div>
                    @endif
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
                                        @if(auth()->user()?->can('invoices.edit'))
                                            <div class="dropdown">
                                                <button
                                                    type="button"
                                                    class="badge {{ $this->statusBadge($selectedInvoice->status) }} border-0 dropdown-toggle"
                                                    data-bs-toggle="dropdown"
                                                >
                                                    {{ $this->statusLabel($selectedInvoice->status) }}
                                                </button>
                                                <ul class="dropdown-menu">
                                                    @foreach($this->statuses() as $key => $label)
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
                                                    <td>{{ $this->money($item->unit_price) }}</td>
                                                    <td class="fw-medium">{{ $this->money($item->total_price) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-4">
                                                        قلمی برای این فاکتور ثبت نشده است.
                                                    </td>
                                                </tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

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
                                                    <span class="text-muted d-block mb-1">بازه ارسال انتخابی</span>
                                                    <span class="fw-medium">
                                                        @if($selectedInvoice->order?->shippingSlot)
                                                            {{ $selectedInvoice->order->shippingSlot->label
                                                                ?? \Illuminate\Support\Carbon::parse($selectedInvoice->order->shippingSlot->date)->translatedFormat('Y/m/d') }}
                                                            @if($selectedInvoice->order->shippingSlot->cost > 0)
                                                                <span class="text-muted small">
                                                                    ({{ $this->money($selectedInvoice->order->shippingSlot->cost) }})
                                                                </span>
                                                            @endif
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
