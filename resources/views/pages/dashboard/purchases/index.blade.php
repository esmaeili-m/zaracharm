<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\AccountingAccount;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Accounting\PurchaseService;
use App\Support\JalaliDate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $info = [];
    public ?Purchase $selectItem = null;

    #[Url(as: 'supplier')]
    public $filterSupplier = '';
    #[Url(as: 'status')]
    public $filterStatus = '';
    public $search = '';

    public $payAccount;
    public $payAmount;
    public $payDate;
    public $payDescription;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('purchases.view'), 403);
        $this->info['header'] = 'خرید کالا';
    }

    public function updating($name)
    {
        if (in_array($name, ['filterSupplier', 'filterStatus', 'search'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function suppliers()
    {
        return Supplier::orderBy('title')->get(['id', 'title']);
    }

    #[Computed]
    public function accounts()
    {
        return AccountingAccount::active()->orderBy('sort')->get();
    }

    #[Computed]
    public function purchases()
    {
        $term = trim((string) $this->search);

        $purchases = Purchase::query()
            ->with(['supplier', 'inventory'])
            ->withCount('items')
            ->when($this->filterSupplier, fn ($q) => $q->where('supplier_id', $this->filterSupplier))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('purchase_number', 'like', "%{$term}%")
                ->orWhere('supplier_invoice_number', 'like', "%{$term}%")
                ->orWhereHas('items', fn ($i) => $i->where('product_name', 'like', "%{$term}%"))))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(20);

        $paid = app(PurchaseService::class)->paidAmounts($purchases->pluck('id')->all());
        $purchases->getCollection()->each(fn ($p) => $p->paid = $paid[$p->id] ?? 0);

        return $purchases;
    }

    public function show($id)
    {
        $this->selectItem = Purchase::with(['items', 'supplier', 'inventory', 'creator', 'payments.account'])->findOrFail($id);
    }

    public function preparePay($id)
    {
        $this->show($id);
        $this->resetValidation();
        $paid = (int) $this->selectItem->payments->sum('amount');
        $this->payAccount = AccountingAccount::default()?->id;
        $this->payAmount = max(0, (int) $this->selectItem->total_amount - $paid) ?: null;
        $this->payDate = JalaliDate::today();
        $this->payDescription = null;
    }

    public function receive()
    {
        abort_if(!auth()->user()->can('purchases.edit'), 403);

        try {
            app(PurchaseService::class)->receive($this->selectItem->id);
        } catch (ValidationException $e) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: collect($e->errors())->flatten()->first());
            return;
        }

        unset($this->purchases);
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'کالاهای خرید ' . $this->selectItem->purchase_number . ' به انبار اضافه شد.');
    }

    public function cancel()
    {
        abort_if(!auth()->user()->can('purchases.delete'), 403);

        try {
            app(PurchaseService::class)->cancel($this->selectItem->id);
        } catch (ValidationException $e) {
            $this->dispatch('alert', type: 'error', title: 'لغو ممکن نیست', text: collect($e->errors())->flatten()->first() . ' بخشی از این کالاها فروخته شده است.');
            return;
        }

        unset($this->purchases);
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'خرید ' . $this->selectItem->purchase_number . ' لغو شد.');
    }

    public function pay()
    {
        abort_if(!auth()->user()->can('purchases.edit'), 403);

        $this->validate([
            'payAccount' => ['required', Rule::exists('accounting_accounts', 'id')->where('status', true)->whereNull('deleted_at')],
            'payAmount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payDate' => ['required', 'regex:' . JalaliDate::PATTERN],
            'payDescription' => ['nullable', 'string', 'max:1000'],
        ], [
            'payAccount.required' => 'حساب پرداخت را انتخاب کنید.',
            'payAccount.exists' => 'حساب انتخاب‌شده معتبر یا فعال نیست.',
            'payAmount.required' => 'مبلغ را وارد کنید.',
            'payAmount.integer' => 'مبلغ باید عدد صحیح باشد.',
            'payAmount.min' => 'مبلغ باید بیشتر از صفر باشد.',
            'payDate.required' => 'تاریخ را وارد کنید.',
            'payDate.regex' => 'تاریخ را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید.',
            'payDescription.max' => 'توضیحات نباید بیشتر از ۱۰۰۰ کاراکتر باشد.',
        ]);

        $date = JalaliDate::toCarbon($this->payDate);
        if (!$date) {
            $this->addError('payDate', 'تاریخ وارد شده معتبر نیست.');
            return;
        }

        if (!$this->selectItem->supplier_id) {
            $this->addError('payAmount', 'این خرید تأمین‌کننده ندارد.');
            return;
        }

        app(PurchaseService::class)->pay(
            $this->selectItem->supplier_id, (int) $this->payAccount, (int) $this->payAmount, $date,
            $this->selectItem->id, filled($this->payDescription) ? trim($this->payDescription) : null
        );

        unset($this->purchases);
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'پرداخت خرید ' . $this->selectItem->purchase_number . ' ثبت شد.');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $info['header'] }}</h1>
            <div class="text-muted small">با «دریافت» خرید، کالاها به انبار اضافه و قیمت خرید محصولات بروز می‌شود.</div>
        </div>
        <div class="btn-list">
            @can('purchases.create')
                <a href="{{ route('purchases.create') }}" class="btn btn-success-light btn-wave">
                    <i class="ri-add-line align-middle"></i> ثبت خرید جدید
                </a>
            @endcan
        </div>
    </div>

    @if(session('purchase_saved'))
        <div class="alert alert-success">{{ session('purchase_saved') }}</div>
    @endif

    @include('pages.dashboard.accounting.partials.nav', ['active' => 'purchases.index'])

    <div class="card custom-card">
        <div class="card-header justify-content-between flex-wrap gap-2">
            <div class="card-title">{{ $info['header'] }}</div>
            <div class="d-flex flex-wrap gap-2">
                <select wire:model.live="filterSupplier" class="form-select form-select-sm w-auto">
                    <option value="">همه تأمین‌کنندگان</option>
                    @foreach($this->suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->title }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterStatus" class="form-select form-select-sm w-auto">
                    <option value="">همه وضعیت‌ها</option>
                    @foreach(\App\Models\Purchase::STATUSES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input wire:model.live.debounce.400ms="search" type="search" class="form-control form-control-sm w-auto" placeholder="شماره خرید / کالا...">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table text-nowrap align-middle">
                    <thead>
                    <tr>
                        <th>شماره</th>
                        <th>تاریخ</th>
                        <th>تأمین‌کننده</th>
                        <th>انبار</th>
                        <th class="text-end">مبلغ</th>
                        <th class="text-end">پرداخت‌شده</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($this->purchases as $purchase)
                        @php($remaining = (int) $purchase->total_amount - (int) $purchase->paid)
                        <tr wire:key="purchase-{{ $purchase->id }}">
                            <td>
                                <a data-bs-toggle="modal" href="#details" wire:click="show({{ $purchase->id }})" class="fw-semibold">{{ $purchase->purchase_number }}</a>
                                <div class="small text-muted">{{ number_format($purchase->items_count) }} قلم{{ $purchase->supplier_invoice_number ? ' | فاکتور ' . $purchase->supplier_invoice_number : '' }}</div>
                            </td>
                            <td class="small">{{ $purchase->jalali_date }}</td>
                            <td>{{ $purchase->supplier?->title ?? '—' }}</td>
                            <td class="small">{{ $purchase->inventory?->title ?? '—' }}</td>
                            <td class="text-end fw-semibold">{{ number_format($purchase->total_amount) }}</td>
                            <td class="text-end">
                                {{ number_format($purchase->paid) }}
                                @if($purchase->status === 'received' && $remaining > 0)
                                    <div class="small text-danger">مانده {{ number_format($remaining) }}</div>
                                @elseif($purchase->status === 'received' && $purchase->total_amount > 0)
                                    <div class="small text-success">تسویه</div>
                                @endif
                            </td>
                            <td><span class="badge bg-{{ $purchase->status_color }}-transparent">{{ $purchase->status_label }}</span></td>
                            <td>
                                <div class="hstack gap-2">
                                    <a data-bs-toggle="modal" href="#details" wire:click="show({{ $purchase->id }})" class="text-primary fs-14 lh-1" title="جزئیات"><i class="ri-eye-line"></i></a>
                                    @if($purchase->status === 'draft')
                                        @can('purchases.edit')
                                            <a href="{{ route('purchases.edit', $purchase) }}" class="text-info fs-14 lh-1" title="ویرایش"><i class="ri-edit-line"></i></a>
                                            <a data-bs-toggle="modal" href="#receive" wire:click="show({{ $purchase->id }})" class="text-success fs-14 lh-1" title="دریافت و ورود به انبار"><i class="ri-inbox-archive-line"></i></a>
                                        @endcan
                                    @endif
                                    @if($purchase->status === 'received' && $remaining > 0 && $purchase->supplier_id)
                                        @can('purchases.edit')
                                            <a data-bs-toggle="modal" href="#pay" wire:click="preparePay({{ $purchase->id }})" class="text-success fs-14 lh-1" title="پرداخت"><i class="ri-hand-coin-line"></i></a>
                                        @endcan
                                    @endif
                                    @if($purchase->status !== 'cancelled')
                                        @can('purchases.delete')
                                            <a data-bs-toggle="modal" href="#cancel" wire:click="show({{ $purchase->id }})" class="text-danger fs-14 lh-1" title="لغو"><i class="ri-close-circle-line"></i></a>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                <strong>خریدی ثبت نشده است.</strong>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $this->purchases->links() }}</div>
        </div>
    </div>

    {{-- جزئیات --}}
    <div wire:ignore.self class="modal fade" id="details">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">خرید {{ $selectItem?->purchase_number }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    @if($selectItem)
                        <div class="row g-2 small mb-3">
                            <div class="col-md-4"><span class="text-muted">تأمین‌کننده:</span> {{ $selectItem->supplier?->title ?? '—' }}</div>
                            <div class="col-md-4"><span class="text-muted">تاریخ:</span> {{ $selectItem->jalali_date }}</div>
                            <div class="col-md-4"><span class="text-muted">انبار:</span> {{ $selectItem->inventory?->title ?? '—' }}</div>
                            <div class="col-md-4"><span class="text-muted">وضعیت:</span> <span class="badge bg-{{ $selectItem->status_color }}-transparent">{{ $selectItem->status_label }}</span></div>
                            <div class="col-md-4"><span class="text-muted">فاکتور فروشنده:</span> {{ $selectItem->supplier_invoice_number ?: '—' }}</div>
                            <div class="col-md-4"><span class="text-muted">ثبت توسط:</span> {{ $selectItem->creator?->mobile ?? '—' }}</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle text-nowrap">
                                <thead><tr><th>کالا</th><th>تعداد</th><th>قیمت خرید واحد</th><th>جمع</th></tr></thead>
                                <tbody>
                                @foreach($selectItem->items as $item)
                                    <tr>
                                        <td>{{ $item->product_name }} <small class="text-muted">{{ $item->variant_name }}</small></td>
                                        <td>{{ number_format($item->quantity) }}</td>
                                        <td>{{ number_format($item->unit_cost) }}</td>
                                        <td>{{ number_format($item->total_cost) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot>
                                @if($selectItem->discount_amount)
                                    <tr><td colspan="3" class="text-end">تخفیف</td><td>{{ number_format($selectItem->discount_amount) }}</td></tr>
                                @endif
                                <tr class="fw-bold"><td colspan="3" class="text-end">مبلغ نهایی</td><td>{{ number_format($selectItem->total_amount) }} تومان</td></tr>
                                </tfoot>
                            </table>
                        </div>
                        @if($selectItem->payments->isNotEmpty())
                            <div class="fw-semibold mb-2">پرداخت‌ها</div>
                            <ul class="list-group mb-2">
                                @foreach($selectItem->payments as $payment)
                                    <li class="list-group-item d-flex justify-content-between small">
                                        <span>{{ $payment->jalali_date }} - {{ $payment->account?->title }}{{ $payment->description ? ' - ' . $payment->description : '' }}</span>
                                        <span class="fw-semibold">{{ number_format($payment->amount) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if($selectItem->note)
                            <div class="small text-muted">{{ $selectItem->note }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- دریافت --}}
    <div wire:ignore.self class="modal fade" id="receive">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">دریافت خرید {{ $selectItem?->purchase_number }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-info mb-0">
                        کالاهای این خرید به انبار «{{ $selectItem?->inventory?->title ?? '—' }}» اضافه می‌شوند
                        @if($selectItem?->update_cost_price) و قیمت خرید محصولات با قیمت این خرید بروز می‌شود @endif.
                        بعد از دریافت، خرید قابل ویرایش نیست.
                    </div>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="receive">
                        <button class="btn btn-success" wire:click="receive()">دریافت و ورود به انبار</button>
                        <button class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                    <div wire:loading wire:target="receive" class="spinner-grow text-info" role="status"><span class="visually-hidden">در حال ثبت...</span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- لغو --}}
    <div wire:ignore.self class="modal fade" id="cancel">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">لغو خرید {{ $selectItem?->purchase_number }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger mb-0">
                        @if($selectItem?->status === 'received')
                            کالاهای این خرید از انبار کم می‌شوند. اگر بخشی از آن‌ها فروخته شده باشد لغو انجام نمی‌شود.
                            پرداخت‌های ثبت‌شده حذف نمی‌شوند و به‌عنوان طلب از تأمین‌کننده باقی می‌مانند.
                        @else
                            از لغو این خرید مطمئن هستید؟
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="cancel">
                        <button class="btn btn-danger" wire:click="cancel()">لغو خرید</button>
                        <button class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                    <div wire:loading wire:target="cancel" class="spinner-grow text-info" role="status"><span class="visually-hidden">در حال لغو...</span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- پرداخت --}}
    <div wire:ignore.self class="modal fade" id="pay">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">پرداخت خرید {{ $selectItem?->purchase_number }} به {{ $selectItem?->supplier?->title }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="pay" id="pay-purchase">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">از حساب</label>
                                <select wire:model="payAccount" class="form-select @error('payAccount') is-invalid @enderror">
                                    <option value="">انتخاب کنید</option>
                                    @foreach($this->accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->title }}</option>
                                    @endforeach
                                </select>
                                @error('payAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاریخ</label>
                                <input data-jdp wire:model.lazy="payDate" type="text" dir="ltr" class="form-control @error('payDate') is-invalid @enderror">
                                @error('payDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">مبلغ (تومان)</label>
                                <input wire:model="payAmount" type="number" min="1" class="form-control @error('payAmount') is-invalid @enderror">
                                @error('payAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیحات</label>
                                <input wire:model="payDescription" type="text" class="form-control @error('payDescription') is-invalid @enderror">
                                @error('payDescription') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="pay">
                        <button type="submit" form="pay-purchase" class="btn btn-success">ثبت پرداخت</button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                    <div wire:loading wire:target="pay" class="spinner-grow text-info" role="status"><span class="visually-hidden">در حال ثبت...</span></div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.js') }}"></script>
        <script>jalaliDatepicker.startWatch();</script>
    @endpush
</div>
