<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\AccountingAccount;
use App\Models\AccountingCategory;
use App\Models\AccountingEntry;
use App\Support\JalaliDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new class extends Component
{
    use WithPagination;
    use WithFileUploads;
    use \App\Traits\FileUploadTrait;

    protected $paginationTheme = 'bootstrap';

    public $info = [];
    public ?AccountingEntry $selectItem = null;

    // فیلترها
    #[Url(as: 'account')]
    public $filterAccount = '';
    #[Url(as: 'type')]
    public $filterType = '';
    public $filterCategory = '';
    #[Url(as: 'source')]
    public $filterSource = '';
    public $filterFrom = '';
    public $filterTo = '';
    #[Url(as: 'q')]
    public $search = '';

    // فرم
    public $type = 'expense';
    public $account_id;
    public $to_account_id;
    public $category_id;
    public $amount;
    public $entry_date;
    public $title;
    public $description;
    public $receipt;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('accounting.view'), 403);

        $this->info['header'] = 'دفتر دریافت و پرداخت';
        $this->info['personal'] = 'ثبت';

        app(\App\Services\Accounting\AccountingSync::class)->run();
    }

    public function updating($name)
    {
        if (str_starts_with($name, 'filter') || $name === 'search') {
            $this->resetPage();
        }
    }

    public function updatedType()
    {
        $this->category_id = null;
        $this->to_account_id = null;
    }

    #[Computed]
    public function accounts()
    {
        return AccountingAccount::orderBy('sort')->get();
    }

    #[Computed]
    public function categories()
    {
        return AccountingCategory::active()->orderBy('sort')->get();
    }

    protected function filteredQuery()
    {
        $from = JalaliDate::toCarbon($this->filterFrom);
        $to = JalaliDate::toCarbon($this->filterTo);

        return AccountingEntry::query()
            ->when($this->filterAccount, fn ($q) => $q->where(fn ($q) => $q->where('account_id', $this->filterAccount)->orWhere('to_account_id', $this->filterAccount)))
            ->when($this->filterType, fn ($q) => $q->where('type', $this->filterType))
            ->when($this->filterCategory, fn ($q) => $q->where('category_id', $this->filterCategory))
            ->when($this->filterSource, fn ($q) => $q->where('source', $this->filterSource))
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->when(trim((string) $this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%' . trim($this->search) . '%')
                ->orWhere('description', 'like', '%' . trim($this->search) . '%')));
    }

    #[Computed]
    public function entries()
    {
        return $this->filteredQuery()
            ->with(['account', 'toAccount', 'category', 'supplier', 'receipt', 'creator'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(25);
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->filteredQuery()
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        // در گردش یک حساب، انتقال ورودی دریافت و انتقال خروجی پرداخت حساب می‌شود
        $transferIn = $transferOut = 0;
        if ($this->filterAccount) {
            $transferIn = (int) $this->filteredQuery()->where('type', 'transfer')->where('to_account_id', $this->filterAccount)->sum('amount');
            $transferOut = (int) $this->filteredQuery()->where('type', 'transfer')->where('account_id', $this->filterAccount)->sum('amount');
        }

        $income = (int) ($rows['income'] ?? 0) + $transferIn;
        $expense = (int) ($rows['expense'] ?? 0) + $transferOut;

        return ['income' => $income, 'expense' => $expense, 'net' => $income - $expense];
    }

    public function clearFilters(): void
    {
        $this->reset('filterAccount', 'filterType', 'filterCategory', 'filterSource', 'filterFrom', 'filterTo', 'search');
        $this->resetPage();
    }

    public function resetData($action = 'create', ?string $type = null)
    {
        $this->reset('selectItem', 'account_id', 'to_account_id', 'category_id', 'amount', 'title', 'description', 'receipt');
        $this->resetValidation();

        if ($action === 'close') {
            $this->dispatch('close-modal');
            return;
        }

        $this->type = $type ?? 'expense';
        $this->entry_date = JalaliDate::today();
        $this->account_id = $this->filterAccount ?: AccountingAccount::default()?->id;
    }

    public function get_data($id)
    {
        $this->selectItem = AccountingEntry::findOrFail($id);
        $this->type = $this->selectItem->type;
        $this->account_id = $this->selectItem->account_id;
        $this->to_account_id = $this->selectItem->to_account_id;
        $this->category_id = $this->selectItem->category_id;
        $this->amount = $this->selectItem->amount;
        $this->entry_date = $this->selectItem->jalali_date;
        $this->title = $this->selectItem->title;
        $this->description = $this->selectItem->description;
        $this->receipt = null;
        $this->resetValidation();
    }

    public function rules()
    {
        $activeAccounts = AccountingAccount::active()->pluck('id')->all();

        // ثبت قبلی ممکن است روی حسابی باشد که بعداً غیرفعال شده
        if ($this->selectItem) {
            $activeAccounts = array_merge($activeAccounts, array_filter([$this->selectItem->account_id, $this->selectItem->to_account_id]));
        }

        return [
            'type' => ['required', Rule::in(array_keys(AccountingEntry::TYPES))],
            'account_id' => ['required', Rule::in($activeAccounts)],
            'to_account_id' => [Rule::requiredIf($this->type === 'transfer'), 'nullable', Rule::in($activeAccounts), 'different:account_id'],
            'category_id' => ['nullable', Rule::exists('accounting_categories', 'id')->where('type', $this->type === 'income' ? 'income' : 'expense')],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'entry_date' => ['required', 'regex:' . JalaliDate::PATTERN],
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages()
    {
        return [
            'type.required' => 'نوع ثبت را انتخاب کنید.',
            'type.in' => 'نوع ثبت معتبر نیست.',
            'account_id.required' => 'حساب را انتخاب کنید.',
            'account_id.in' => 'حساب انتخاب‌شده معتبر یا فعال نیست.',
            'to_account_id.required' => 'حساب مقصد را انتخاب کنید.',
            'to_account_id.in' => 'حساب مقصد معتبر یا فعال نیست.',
            'to_account_id.different' => 'حساب مبدأ و مقصد نمی‌تواند یکی باشد.',
            'category_id.exists' => 'دسته انتخاب‌شده با نوع ثبت هم‌خوانی ندارد.',
            'amount.required' => 'مبلغ را وارد کنید.',
            'amount.integer' => 'مبلغ باید عدد صحیح (تومان) باشد.',
            'amount.min' => 'مبلغ باید بیشتر از صفر باشد.',
            'amount.max' => 'مبلغ خارج از محدوده مجاز است.',
            'entry_date.required' => 'تاریخ را وارد کنید.',
            'entry_date.regex' => 'تاریخ را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید.',
            'title.required' => 'شرح را وارد کنید.',
            'title.min' => 'شرح باید حداقل ۲ کاراکتر باشد.',
            'title.max' => 'شرح نباید بیشتر از ۲۰۰ کاراکتر باشد.',
            'description.max' => 'توضیحات نباید بیشتر از ۲۰۰۰ کاراکتر باشد.',
            'receipt.file' => 'فایل رسید معتبر نیست.',
            'receipt.mimes' => 'رسید باید تصویر (jpg, png, webp) یا pdf باشد.',
            'receipt.max' => 'حجم رسید نباید بیشتر از ۵ مگابایت باشد.',
        ];
    }

    public function save()
    {
        abort_if(!auth()->user()->can($this->selectItem ? 'accounting.edit' : 'accounting.create'), 403);

        if ($this->selectItem?->isAuto()) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'ثبت‌های خودکار (پرداخت سفارش / فاکتور) قابل ویرایش نیستند.');
            return;
        }

        $this->validate();

        $date = JalaliDate::toCarbon($this->entry_date);
        if (!$date) {
            $this->addError('entry_date', 'تاریخ وارد شده معتبر نیست.');
            return;
        }

        $data = [
            'type' => $this->type,
            'account_id' => (int) $this->account_id,
            'to_account_id' => $this->type === 'transfer' ? (int) $this->to_account_id : null,
            'category_id' => $this->type === 'transfer' ? null : ($this->category_id ?: null),
            'amount' => (int) $this->amount,
            'entry_date' => $date->toDateString(),
            'title' => trim($this->title),
            'description' => filled($this->description) ? trim($this->description) : null,
        ];

        DB::transaction(function () use ($data) {
            if ($this->selectItem) {
                // پرداخت به تأمین‌کننده نوع و منبعش را حفظ می‌کند
                if ($this->selectItem->source === 'supplier_payment') {
                    unset($data['type'], $data['category_id'], $data['to_account_id']);
                }
                $entry = tap($this->selectItem)->update($data);
            } else {
                $entry = AccountingEntry::create($data + ['source' => 'manual', 'created_by' => auth()->id()]);
            }

            if ($this->receipt) {
                $entry->media()->where('collection', 'receipt')->get()->each(fn ($m) => $this->deleteMedia($m));
                $this->upload($this->receipt, $entry, 'receipt');
            }
        });

        unset($this->entries, $this->totals);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق',
            text: $this->selectItem ? 'ثبت ویرایش شد.' : 'ثبت جدید ذخیره شد.');
        $this->resetData('close');
    }

    public function delete()
    {
        abort_if(!auth()->user()->can('accounting.delete'), 403);

        if ($this->selectItem) {
            // ثبت خودکار حذف‌شده با همگام‌سازی بعدی دوباره ساخته نمی‌شود (unique روی ردیف حذف‌شده)
            $this->selectItem->delete();
            unset($this->entries, $this->totals);
            $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'ثبت حذف شد.');
            $this->resetData('close');
        }
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $info['header'] }}</h1>
            <div class="text-muted small">پرداخت‌های سفارش‌ها و فروش حضوری به‌صورت خودکار ثبت می‌شوند؛ هزینه‌ها، درآمدهای دیگر و انتقال بین حساب‌ها را اینجا ثبت کنید.</div>
        </div>
        <div class="btn-list">
            @can('accounting.create')
                <button wire:click="resetData('create', 'expense')" data-bs-toggle="modal" href="#create" class="btn btn-danger-light btn-wave">
                    <i class="ri-indeterminate-circle-line align-middle"></i> ثبت هزینه
                </button>
                <button wire:click="resetData('create', 'income')" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave">
                    <i class="ri-add-circle-line align-middle"></i> ثبت درآمد
                </button>
                <button wire:click="resetData('create', 'transfer')" data-bs-toggle="modal" href="#create" class="btn btn-info-light btn-wave">
                    <i class="ri-arrow-left-right-line align-middle"></i> انتقال
                </button>
            @endcan
        </div>
    </div>

    @include('pages.dashboard.accounting.partials.nav', ['active' => 'accounting.entries'])

    {{-- فیلترها --}}
    <div class="card custom-card">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label small mb-1">حساب</label>
                    <select wire:model.live="filterAccount" class="form-select form-select-sm">
                        <option value="">همه حساب‌ها</option>
                        @foreach($this->accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">نوع</label>
                    <select wire:model.live="filterType" class="form-select form-select-sm">
                        <option value="">همه</option>
                        @foreach(\App\Models\AccountingEntry::TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">دسته</label>
                    <select wire:model.live="filterCategory" class="form-select form-select-sm">
                        <option value="">همه</option>
                        @foreach($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->title }} ({{ $category->type_label }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">منبع</label>
                    <select wire:model.live="filterSource" class="form-select form-select-sm">
                        <option value="">همه</option>
                        @foreach(\App\Models\AccountingEntry::SOURCES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-12">
                    <label class="form-label small mb-1">جستجو در شرح</label>
                    <input wire:model.live.debounce.500ms="search" type="search" class="form-control form-control-sm" placeholder="شماره سفارش، عنوان هزینه...">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">از تاریخ</label>
                    <input data-jdp wire:model.lazy="filterFrom" type="text" dir="ltr" class="form-control form-control-sm" placeholder="1405/01/01">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">تا تاریخ</label>
                    <input data-jdp wire:model.lazy="filterTo" type="text" dir="ltr" class="form-control form-control-sm" placeholder="1405/12/29">
                </div>
                <div class="col-md-2 col-12">
                    <button type="button" wire:click="clearFilters" class="btn btn-sm btn-light w-100">حذف فیلترها</button>
                </div>
            </div>
        </div>
    </div>

    {{-- جمع‌ها --}}
    <div class="row">
        <div class="col-md-4">
            <div class="card custom-card"><div class="card-body">
                <div class="text-muted small mb-1">{{ $filterAccount ? 'ورودی به حساب' : 'جمع دریافت‌ها' }}</div>
                <div class="fs-18 fw-bold text-success">{{ number_format($this->totals['income']) }} <small class="fs-12 text-muted">تومان</small></div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card custom-card"><div class="card-body">
                <div class="text-muted small mb-1">{{ $filterAccount ? 'خروجی از حساب' : 'جمع پرداخت‌ها' }}</div>
                <div class="fs-18 fw-bold text-danger">{{ number_format($this->totals['expense']) }} <small class="fs-12 text-muted">تومان</small></div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card custom-card"><div class="card-body">
                <div class="text-muted small mb-1">خالص (فیلتر فعلی)</div>
                <div class="fs-18 fw-bold {{ $this->totals['net'] < 0 ? 'text-danger' : 'text-primary' }}">{{ number_format($this->totals['net']) }} <small class="fs-12 text-muted">تومان</small></div>
            </div></div>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table text-nowrap align-middle">
                    <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>شرح</th>
                        <th>نوع / دسته</th>
                        <th>حساب</th>
                        <th class="text-end">مبلغ (تومان)</th>
                        <th>عملیات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($this->entries as $entry)
                        @php
                            $isIn = $entry->type === 'income' || ($entry->type === 'transfer' && $filterAccount && (int) $entry->to_account_id === (int) $filterAccount);
                            $color = $entry->type === 'transfer' && !$filterAccount ? 'info' : ($isIn ? 'success' : 'danger');
                        @endphp
                        <tr wire:key="entry-{{ $entry->id }}">
                            <td class="small">{{ $entry->jalali_date }}</td>
                            <td style="white-space: normal; min-width: 220px">
                                <div class="fw-semibold">{{ $entry->title }}</div>
                                <div class="small text-muted d-flex flex-wrap gap-2">
                                    <span class="badge bg-light text-muted">{{ $entry->source_label }}</span>
                                    @if($entry->supplier)
                                        <span><i class="ri-truck-line"></i> {{ $entry->supplier->title }}</span>
                                    @endif
                                    @if($entry->description)
                                        <span>{{ \Illuminate\Support\Str::limit($entry->description, 60) }}</span>
                                    @endif
                                    @if($entry->receipt_url)
                                        <a href="{{ $entry->receipt_url }}" target="_blank" rel="noopener"><i class="ri-attachment-2"></i> رسید</a>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $color }}-transparent">{{ $entry->type_label }}</span>
                                @if($entry->category)
                                    <div class="small text-muted mt-1">{{ $entry->category->title }}</div>
                                @endif
                            </td>
                            <td class="small">
                                {{ $entry->account?->title }}
                                @if($entry->type === 'transfer')
                                    <i class="ri-arrow-left-line mx-1"></i>{{ $entry->toAccount?->title }}
                                @endif
                            </td>
                            <td class="text-end fw-bold text-{{ $color }}">
                                {{ $entry->type === 'transfer' && !$filterAccount ? '' : ($isIn ? '+' : '−') }}{{ number_format($entry->amount) }}
                            </td>
                            <td>
                                <div class="hstack gap-2">
                                    @if(!$entry->isAuto())
                                        @can('accounting.edit')
                                            <a data-bs-toggle="modal" href="#create" wire:click="get_data({{ $entry->id }})" class="text-info fs-14 lh-1" title="ویرایش"><i class="ri-edit-line"></i></a>
                                        @endcan
                                    @else
                                        <i class="ri-lock-line text-muted" title="ثبت خودکار؛ فقط قابل حذف"></i>
                                    @endif
                                    @can('accounting.delete')
                                        <a data-bs-toggle="modal" href="#delete" wire:click="get_data({{ $entry->id }})" class="text-danger fs-14 lh-1" title="حذف"><i class="ri-delete-bin-5-line"></i></a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                <strong>ثبتی با این فیلترها پیدا نشد.</strong>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $this->entries->links() }}</div>
        </div>
    </div>

    {{-- فرم ثبت --}}
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $selectItem ? 'ویرایش ثبت' : 'ثبت ' . (\App\Models\AccountingEntry::TYPES[$type] ?? '') }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="save" id="save-entry">
                        <div class="row g-3">
                            @if(!$selectItem || $selectItem->source === 'manual')
                                <div class="col-12">
                                    <div class="btn-group w-100" role="group">
                                        @foreach(['expense' => 'danger', 'income' => 'success', 'transfer' => 'info'] as $key => $color)
                                            <input type="radio" class="btn-check" wire:model.live="type" value="{{ $key }}" id="entry-type-{{ $key }}">
                                            <label class="btn btn-outline-{{ $color }}" for="entry-type-{{ $key }}">{{ \App\Models\AccountingEntry::TYPES[$key] }}</label>
                                        @endforeach
                                    </div>
                                    @error('type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            <div class="col-md-6">
                                <label class="form-label">{{ $type === 'transfer' ? 'از حساب' : 'حساب' }}</label>
                                <select wire:model="account_id" class="form-select @error('account_id') is-invalid @enderror">
                                    <option value="">انتخاب کنید</option>
                                    @foreach($this->accounts as $account)
                                        @if($account->status || (int) $account->id === (int) $account_id)
                                            <option value="{{ $account->id }}">{{ $account->title }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if($type === 'transfer')
                                <div class="col-md-6">
                                    <label class="form-label">به حساب</label>
                                    <select wire:model="to_account_id" class="form-select @error('to_account_id') is-invalid @enderror">
                                        <option value="">انتخاب کنید</option>
                                        @foreach($this->accounts as $account)
                                            @if($account->status || (int) $account->id === (int) $to_account_id)
                                                <option value="{{ $account->id }}">{{ $account->title }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    @error('to_account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @elseif(!$selectItem || $selectItem->source === 'manual')
                                <div class="col-md-6">
                                    <label class="form-label">دسته</label>
                                    <select wire:model="category_id" class="form-select @error('category_id') is-invalid @enderror">
                                        <option value="">بدون دسته</option>
                                        @foreach($this->categories->where('type', $type === 'income' ? 'income' : 'expense') as $category)
                                            <option value="{{ $category->id }}">{{ $category->title }}{{ $category->affects_profit ? '' : ' (خارج از سود و زیان)' }}</option>
                                        @endforeach
                                    </select>
                                    @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @can('accounting.create')
                                        <a href="{{ route('accounting.categories') }}" class="small">مدیریت دسته‌ها</a>
                                    @endcan
                                </div>
                            @endif

                            <div class="col-md-6">
                                <label class="form-label">مبلغ (تومان)</label>
                                <input wire:model="amount" type="number" min="1" class="form-control @error('amount') is-invalid @enderror">
                                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاریخ</label>
                                <input data-jdp wire:model.lazy="entry_date" type="text" dir="ltr" class="form-control @error('entry_date') is-invalid @enderror" placeholder="1405/01/31">
                                @error('entry_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">شرح</label>
                                <input wire:model="title" type="text" class="form-control @error('title') is-invalid @enderror"
                                       placeholder="{{ $type === 'transfer' ? 'مثلاً: واریز موجودی صندوق به بانک' : ($type === 'income' ? 'مثلاً: تسویه دیجی‌کالا' : 'مثلاً: اجاره آبان') }}">
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیحات</label>
                                <textarea wire:model="description" rows="2" class="form-control @error('description') is-invalid @enderror"></textarea>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">رسید / فاکتور (اختیاری)</label>
                                <input wire:model="receipt" type="file" accept="image/*,application/pdf" class="form-control @error('receipt') is-invalid @enderror">
                                <div wire:loading wire:target="receipt" class="small text-muted mt-1">در حال بارگذاری...</div>
                                @error('receipt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if($selectItem?->receipt_url && !$receipt)
                                    <a href="{{ $selectItem->receipt_url }}" target="_blank" rel="noopener" class="small"><i class="ri-attachment-2"></i> رسید فعلی</a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="save, receipt">
                        <button type="submit" form="save-entry" class="btn btn-primary">ذخیره</button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                    <div wire:loading wire:target="save" class="spinner-grow text-info" role="status"><span class="visually-hidden">در حال ذخیره...</span></div>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">حذف ثبت {{ $selectItem?->title }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger mb-0">
                        از حذف این ثبت مطمئن هستید؟ مانده حساب اصلاح می‌شود.
                        @if($selectItem?->isAuto())
                            <div class="mt-2 small">این ثبت خودکار است و بعد از حذف دوباره ساخته نمی‌شود.</div>
                        @endif
                    </div>
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
        <script>
            jalaliDatepicker.startWatch();
        </script>
    @endpush
</div>
