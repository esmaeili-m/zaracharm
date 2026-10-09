<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\AccountingAccount;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Accounting\PurchaseService;
use App\Support\JalaliDate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    public $info = [];
    public $data;
    public array $balances = [];
    public array $purchaseTotals = [];
    public $selectItem;
    public Supplier $model;
    public $search = '';

    public $title;
    public $contact_name;
    public $mobile;
    public $phone;
    public $address;
    public $opening_balance = 0;
    public $description;
    public $status = true;

    // پرداخت
    public $payAccount;
    public $payAmount;
    public $payDate;
    public $payPurchase;
    public $payDescription;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Supplier $model)
    {
        abort_if(!auth()->user()->can('purchases.view'), 403);
        $this->model = $model;
        $this->info['header'] = 'تأمین‌کنندگان';
        $this->info['create'] = 'افزودن تأمین‌کننده';
        $this->info['delete'] = 'حذف تأمین‌کننده';
        $this->info['table']['headers'] = ['#', 'تأمین‌کننده', 'تماس', 'جمع خرید', 'مانده بدهی', 'وضعیت', 'عملیات'];
        $this->loadData();
    }

    public function updatedSearch()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $term = trim((string) $this->search);

        $this->data = $this->model->newQuery()
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")))
            ->orderBy('title')
            ->get();

        $this->balances = Supplier::balances();
        $this->purchaseTotals = Purchase::where('status', 'received')
            ->selectRaw('supplier_id, SUM(total_amount) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    #[Computed]
    public function accounts()
    {
        return AccountingAccount::active()->orderBy('sort')->get();
    }

    #[Computed]
    public function openPurchases()
    {
        if (!$this->selectItem) {
            return collect();
        }

        $purchases = Purchase::where('supplier_id', $this->selectItem->id)->where('status', 'received')->latest('purchase_date')->get();
        $paid = app(PurchaseService::class)->paidAmounts($purchases->pluck('id')->all());

        return $purchases->each(fn ($p) => $p->remaining = (int) $p->total_amount - ($paid[$p->id] ?? 0))
            ->filter(fn ($p) => $p->remaining > 0)
            ->values();
    }

    public function get_data($id)
    {
        $this->selectItem = $this->model->findOrFail($id);
        foreach (['title', 'contact_name', 'mobile', 'phone', 'address', 'opening_balance', 'description'] as $field) {
            $this->{$field} = $this->selectItem->{$field};
        }
        $this->status = (bool) $this->selectItem->status;
        $this->resetValidation();
    }

    public function preparePay($id)
    {
        $this->get_data($id);
        unset($this->openPurchases);
        $this->payAccount = AccountingAccount::default()?->id;
        $this->payAmount = max(0, $this->balances[$id] ?? 0) ?: null;
        $this->payDate = JalaliDate::today();
        $this->payPurchase = '';
        $this->payDescription = null;
    }

    public function updatedPayPurchase($value)
    {
        $purchase = $this->openPurchases->firstWhere('id', (int) $value);
        if ($purchase) {
            $this->payAmount = $purchase->remaining;
        }
    }

    public function resetData($action = 'create')
    {
        if ($action == 'create') {
            $this->resetExcept('model', 'info', 'data', 'balances', 'purchaseTotals', 'search');
        } else {
            $this->resetExcept(['selectItem', 'model', 'info', 'data', 'balances', 'purchaseTotals', 'search']);
            $this->dispatch('close-modal');
        }
        $this->resetValidation();
    }

    public function rules()
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'mobile' => ['nullable', 'regex:/^09\d{9}$/'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'opening_balance' => ['required', 'integer', 'between:-999999999999,999999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['boolean'],
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'نام تأمین‌کننده الزامی است.',
            'title.min' => 'نام تأمین‌کننده باید حداقل ۲ کاراکتر باشد.',
            'title.max' => 'نام تأمین‌کننده نباید بیشتر از ۲۰۰ کاراکتر باشد.',
            'contact_name.max' => 'نام رابط نباید بیشتر از ۲۰۰ کاراکتر باشد.',
            'mobile.regex' => 'شماره موبایل باید با ۰۹ شروع شود و ۱۱ رقم باشد.',
            'phone.max' => 'شماره تلفن نباید بیشتر از ۲۰ کاراکتر باشد.',
            'address.max' => 'آدرس نباید بیشتر از ۱۰۰۰ کاراکتر باشد.',
            'opening_balance.required' => 'مانده اول دوره را وارد کنید (صفر هم مجاز است).',
            'opening_balance.integer' => 'مانده اول دوره باید عدد صحیح باشد.',
            'opening_balance.between' => 'مانده اول دوره خارج از محدوده مجاز است.',
            'description.max' => 'توضیحات نباید بیشتر از ۲۰۰۰ کاراکتر باشد.',
        ];
    }

    public function save()
    {
        abort_if(!auth()->user()->can($this->selectItem ? 'purchases.edit' : 'purchases.create'), 403);

        $data = $this->validate();

        $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);

        $this->loadData();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق',
            text: $this->selectItem ? 'تأمین‌کننده ویرایش شد.' : 'تأمین‌کننده جدید ایجاد شد.');
        $this->resetData('close');
    }

    public function pay()
    {
        abort_if(!auth()->user()->can('purchases.edit'), 403);

        $this->validate([
            'payAccount' => ['required', Rule::exists('accounting_accounts', 'id')->where('status', true)->whereNull('deleted_at')],
            'payAmount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payDate' => ['required', 'regex:' . JalaliDate::PATTERN],
            'payPurchase' => ['nullable', Rule::exists('purchases', 'id')->where('supplier_id', $this->selectItem?->id)],
            'payDescription' => ['nullable', 'string', 'max:1000'],
        ], [
            'payAccount.required' => 'حساب پرداخت را انتخاب کنید.',
            'payAccount.exists' => 'حساب انتخاب‌شده معتبر یا فعال نیست.',
            'payAmount.required' => 'مبلغ را وارد کنید.',
            'payAmount.integer' => 'مبلغ باید عدد صحیح باشد.',
            'payAmount.min' => 'مبلغ باید بیشتر از صفر باشد.',
            'payDate.required' => 'تاریخ را وارد کنید.',
            'payDate.regex' => 'تاریخ را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید.',
            'payPurchase.exists' => 'خرید انتخاب‌شده متعلق به این تأمین‌کننده نیست.',
            'payDescription.max' => 'توضیحات نباید بیشتر از ۱۰۰۰ کاراکتر باشد.',
        ]);

        $date = JalaliDate::toCarbon($this->payDate);
        if (!$date) {
            $this->addError('payDate', 'تاریخ وارد شده معتبر نیست.');
            return;
        }

        try {
            app(PurchaseService::class)->pay(
                $this->selectItem->id, (int) $this->payAccount, (int) $this->payAmount, $date,
                $this->payPurchase ? (int) $this->payPurchase : null,
                filled($this->payDescription) ? trim($this->payDescription) : null
            );
        } catch (ValidationException $e) {
            $this->addError('payAmount', collect($e->errors())->flatten()->first());
            return;
        }

        $this->loadData();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'پرداخت به ' . $this->selectItem->title . ' ثبت شد.');
        $this->resetData('close');
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('purchases.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);
        $this->loadData();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'وضعیت تأمین‌کننده بروز شد.');
    }

    public function delete()
    {
        abort_if(!auth()->user()->can('purchases.delete'), 403);

        if (!$this->selectItem) {
            return;
        }

        if (($this->balances[$this->selectItem->id] ?? 0) !== 0) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'مانده حساب این تأمین‌کننده صفر نیست و قابل حذف نیست.');
            return;
        }

        $this->selectItem->delete();
        $this->loadData();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'تأمین‌کننده حذف شد.');
        $this->resetData('close');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $info['header'] }}</h1>
            <div class="text-muted small">مانده بدهی = مانده اول دوره + خریدهای دریافت‌شده − پرداخت‌ها. منفی یعنی تأمین‌کننده به شما بدهکار است.</div>
        </div>
        <div class="btn-list">
            @can('purchases.create')
                <a href="{{ route('purchases.create') }}" class="btn btn-primary-light btn-wave">
                    <i class="ri-shopping-basket-2-line align-middle"></i> ثبت خرید
                </a>
                <button wire:click="resetData()" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave">
                    <i class="ri-add-line align-middle"></i> {{ $info['create'] }}
                </button>
            @endcan
        </div>
    </div>

    @include('pages.dashboard.accounting.partials.nav', ['active' => 'suppliers.index'])

    <div class="card custom-card">
        <div class="card-header justify-content-between">
            <div class="card-title">{{ $info['header'] }}</div>
            <input wire:model.live.debounce.400ms="search" type="search" class="form-control form-control-sm w-auto" placeholder="جستجو نام، رابط، موبایل...">
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table text-nowrap align-middle">
                    <thead>
                    <tr>
                        @foreach($info['table']['headers'] as $h)
                            <th>{{ $h }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($data as $item)
                        @php($balance = $balances[$item->id] ?? 0)
                        <tr wire:key="supplier-{{ $item->id }}">
                            <th>{{ $loop->iteration }}</th>
                            <td>
                                <div class="fw-semibold">{{ $item->title }}</div>
                                @if($item->contact_name)
                                    <small class="text-muted">{{ $item->contact_name }}</small>
                                @endif
                            </td>
                            <td class="small" dir="ltr">
                                {{ $item->mobile }}{{ $item->mobile && $item->phone ? ' / ' : '' }}{{ $item->phone }}
                            </td>
                            <td>{{ number_format($purchaseTotals[$item->id] ?? 0) }}</td>
                            <td class="fw-bold {{ $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-success' : '') }}">
                                {{ number_format(abs($balance)) }}
                                @if($balance < 0) <small class="fw-normal">(بستانکار)</small> @endif
                            </td>
                            <td>
                                @can('purchases.edit')
                                    <span style="cursor: pointer" wire:click="change_status({{ $item->id }})"
                                          class="badge bg-outline-{{ $item->status ? 'success' : 'danger' }}">{{ $item->status ? 'فعال' : 'غیرفعال' }}</span>
                                @else
                                    <span class="badge bg-outline-{{ $item->status ? 'success' : 'danger' }}">{{ $item->status ? 'فعال' : 'غیرفعال' }}</span>
                                @endcan
                            </td>
                            <td>
                                <div class="hstack gap-2">
                                    @can('purchases.edit')
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#pay" wire:click="preparePay({{ $item->id }})" class="btn btn-sm btn-success-light">
                                            <i class="ri-hand-coin-line"></i> پرداخت
                                        </button>
                                    @endcan
                                    <a href="{{ route('purchases.index', ['supplier' => $item->id]) }}" class="text-primary fs-14 lh-1" title="خریدها"><i class="ri-shopping-basket-2-line"></i></a>
                                    @can('accounting.view')
                                        <a href="{{ route('accounting.entries', ['source' => 'supplier_payment', 'q' => $item->title]) }}" class="text-secondary fs-14 lh-1" title="پرداخت‌ها"><i class="ri-file-list-3-line"></i></a>
                                    @endcan
                                    @can('purchases.edit')
                                        <a data-bs-toggle="modal" href="#create" wire:click="get_data({{ $item->id }})" class="text-info fs-14 lh-1"><i class="ri-edit-line"></i></a>
                                    @endcan
                                    @can('purchases.delete')
                                        <a data-bs-toggle="modal" href="#delete" wire:click="get_data({{ $item->id }})" class="text-danger fs-14 lh-1"><i class="ri-delete-bin-5-line"></i></a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($info['table']['headers']) }}" class="text-center py-5 text-muted">
                                <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                <strong>تأمین‌کننده‌ای ثبت نشده است.</strong>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $selectItem ? 'ویرایش تأمین‌کننده' : $info['create'] }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="save" id="save-supplier">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">نام تأمین‌کننده</label>
                                <input wire:model="title" type="text" class="form-control @error('title') is-invalid @enderror" placeholder="مثلاً: تولیدی چرم آریا">
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نام رابط</label>
                                <input wire:model="contact_name" type="text" class="form-control @error('contact_name') is-invalid @enderror">
                                @error('contact_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">موبایل</label>
                                <input wire:model="mobile" type="text" dir="ltr" class="form-control @error('mobile') is-invalid @enderror" placeholder="09xxxxxxxxx">
                                @error('mobile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تلفن</label>
                                <input wire:model="phone" type="text" dir="ltr" class="form-control @error('phone') is-invalid @enderror">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">آدرس</label>
                                <input wire:model="address" type="text" class="form-control @error('address') is-invalid @enderror">
                                @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">مانده اول دوره (تومان)</label>
                                <input wire:model="opening_balance" type="number" class="form-control @error('opening_balance') is-invalid @enderror">
                                @error('opening_balance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">مثبت = بدهی شما به تأمین‌کننده؛ منفی = طلب شما.</div>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch">
                                    <input wire:model="status" class="form-check-input" type="checkbox" id="supplier_status">
                                    <label class="form-check-label" for="supplier_status">فعال</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیحات</label>
                                <textarea wire:model="description" rows="2" class="form-control @error('description') is-invalid @enderror"></textarea>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="save">
                        <button type="submit" form="save-supplier" class="btn btn-primary">ذخیره</button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                    <div wire:loading wire:target="save" class="spinner-grow text-info" role="status"><span class="visually-hidden">در حال ذخیره...</span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- پرداخت به تأمین‌کننده --}}
    <div wire:ignore.self class="modal fade" id="pay">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">پرداخت به {{ $selectItem?->title }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    @if($selectItem)
                        @php($currentBalance = $balances[$selectItem->id] ?? 0)
                        <div class="alert alert-{{ $currentBalance > 0 ? 'warning' : 'light' }} py-2 small">
                            مانده بدهی فعلی: <strong>{{ number_format(max(0, $currentBalance)) }}</strong> تومان
                        </div>
                    @endif
                    <form wire:submit.prevent="pay" id="pay-supplier">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">بابت خرید (اختیاری)</label>
                                <select wire:model.live="payPurchase" class="form-select @error('payPurchase') is-invalid @enderror">
                                    <option value="">پرداخت علی‌الحساب</option>
                                    @foreach($this->openPurchases as $purchase)
                                        <option value="{{ $purchase->id }}">{{ $purchase->purchase_number }} - {{ $purchase->jalali_date }} (مانده {{ number_format($purchase->remaining) }})</option>
                                    @endforeach
                                </select>
                                @error('payPurchase') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
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
                                <input wire:model="payDescription" type="text" class="form-control @error('payDescription') is-invalid @enderror" placeholder="مثلاً: شماره پیگیری انتقال">
                                @error('payDescription') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="pay">
                        <button type="submit" form="pay-supplier" class="btn btn-success">ثبت پرداخت</button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                    <div wire:loading wire:target="pay" class="spinner-grow text-info" role="status"><span class="visually-hidden">در حال ثبت...</span></div>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $info['delete'] }} {{ $selectItem?->title }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger mb-0">از حذف این تأمین‌کننده مطمئن هستید؟ فقط تأمین‌کننده با مانده صفر قابل حذف است.</div>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="delete">
                        <button class="btn btn-danger" wire:click="delete()">حذف</button>
                        <button class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                    <div wire:loading wire:target="delete" class="spinner-grow text-info" role="status"><span class="visually-hidden">در حال حذف...</span></div>
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
