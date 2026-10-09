<?php

use Livewire\Component;
use App\Models\AccountingAccount;
use App\Models\BankCard;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new class extends Component
{
    public $info = [];
    public $data;
    public array $balances = [];
    public $selectItem;
    public AccountingAccount $model;

    public $title;
    public $type = 'bank';
    public $account_number;
    public $bank_card_id;
    public $payment_gateway_id;
    public $opening_balance = 0;
    public $is_default = false;
    public $status = true;
    public $description;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(AccountingAccount $model)
    {
        abort_if(!auth()->user()->can('accounting.view'), 403);
        $this->model = $model;
        $this->info['header'] = 'حساب‌ها (صندوق و بانک)';
        $this->info['create'] = 'افزودن حساب';
        $this->info['delete'] = 'حذف حساب';
        $this->info['table']['headers'] = ['#', 'عنوان', 'نوع', 'اتصال خودکار', 'مانده', 'وضعیت', 'عملیات'];

        app(\App\Services\Accounting\AccountingSync::class)->run();
        $this->loadData();
    }

    public function loadData()
    {
        $this->data = $this->model->newQuery()->with(['bankCard', 'gateway'])->orderBy('sort')->get();
        $this->balances = AccountingAccount::balances();
    }

    public function bankCards()
    {
        return BankCard::orderBy('sort')->get();
    }

    public function gateways()
    {
        return PaymentGateway::orderBy('sort')->get();
    }

    public function get_data($id)
    {
        $this->selectItem = $this->model->findOrFail($id);
        foreach (['title', 'type', 'account_number', 'bank_card_id', 'payment_gateway_id', 'opening_balance', 'description'] as $field) {
            $this->{$field} = $this->selectItem->{$field};
        }
        $this->is_default = (bool) $this->selectItem->is_default;
        $this->status = (bool) $this->selectItem->status;
        $this->resetValidation();
    }

    public function resetData($action = 'create')
    {
        if ($action == 'create') {
            $this->resetExcept('model', 'info', 'data', 'balances');
        } else {
            $this->resetExcept(['selectItem', 'model', 'info', 'data', 'balances']);
            $this->dispatch('close-modal');
        }
        $this->resetValidation();
    }

    public function rules()
    {
        $id = $this->selectItem?->id;

        return [
            'title' => ['required', 'string', 'min:2', 'max:150'],
            'type' => ['required', Rule::in(array_keys(AccountingAccount::TYPES))],
            'account_number' => ['nullable', 'string', 'max:64'],
            // هر کارت / درگاه فقط به یک حساب وصل شود
            'bank_card_id' => ['nullable', 'exists:bank_cards,id', Rule::unique('accounting_accounts', 'bank_card_id')->ignore($id)->whereNull('deleted_at')],
            'payment_gateway_id' => ['nullable', 'exists:payment_gateways,id', Rule::unique('accounting_accounts', 'payment_gateway_id')->ignore($id)->whereNull('deleted_at')],
            'opening_balance' => ['required', 'integer', 'between:-999999999999,999999999999'],
            'is_default' => ['boolean'],
            'status' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'عنوان حساب الزامی است.',
            'title.min' => 'عنوان حساب باید حداقل ۲ کاراکتر باشد.',
            'title.max' => 'عنوان حساب نباید بیشتر از ۱۵۰ کاراکتر باشد.',
            'type.required' => 'نوع حساب را انتخاب کنید.',
            'type.in' => 'نوع حساب معتبر نیست.',
            'account_number.max' => 'شماره حساب نباید بیشتر از ۶۴ کاراکتر باشد.',
            'bank_card_id.exists' => 'کارت انتخاب‌شده معتبر نیست.',
            'bank_card_id.unique' => 'این کارت قبلاً به حساب دیگری وصل شده است.',
            'payment_gateway_id.exists' => 'درگاه انتخاب‌شده معتبر نیست.',
            'payment_gateway_id.unique' => 'این درگاه قبلاً به حساب دیگری وصل شده است.',
            'opening_balance.required' => 'مانده اول دوره را وارد کنید (صفر هم مجاز است).',
            'opening_balance.integer' => 'مانده اول دوره باید عدد صحیح باشد.',
            'opening_balance.between' => 'مانده اول دوره خارج از محدوده مجاز است.',
            'description.max' => 'توضیحات نباید بیشتر از ۱۰۰۰ کاراکتر باشد.',
        ];
    }

    public function save()
    {
        abort_if(!auth()->user()->can($this->selectItem ? 'accounting.edit' : 'accounting.create'), 403);

        $data = $this->validate();
        $data['bank_card_id'] = $data['bank_card_id'] ?: null;
        $data['payment_gateway_id'] = $data['payment_gateway_id'] ?: null;

        if ($this->selectItem?->is_default && !$data['is_default']) {
            $this->addError('is_default', 'حداقل یک حساب باید پیش‌فرض باشد؛ ابتدا حساب دیگری را پیش‌فرض کنید.');
            return;
        }

        if ($data['is_default']) {
            $data['status'] = true;
        }

        DB::transaction(function () use ($data) {
            $account = $this->selectItem
                ? tap($this->selectItem)->update($data)
                : $this->model->create($data + ['sort' => (int) $this->model->max('sort') + 1]);

            if ($data['is_default']) {
                $this->model->newQuery()->whereKeyNot($account->id)->update(['is_default' => false]);
            }
        });

        $this->loadData();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق',
            text: $this->selectItem ? 'حساب ویرایش شد.' : 'حساب جدید ایجاد شد.');
        $this->resetData('close');
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('accounting.edit'), 403);

        $item = $this->model->findOrFail($id);

        if ($item->is_default && $item->status) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'حساب پیش‌فرض را نمی‌توان غیرفعال کرد.');
            return;
        }

        $item->update(['status' => !$item->status]);
        $this->loadData();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'وضعیت حساب بروز شد.');
    }

    public function delete()
    {
        abort_if(!auth()->user()->can('accounting.delete'), 403);

        if (!$this->selectItem) {
            return;
        }

        if ($this->selectItem->is_default) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'حساب پیش‌فرض قابل حذف نیست.');
            return;
        }

        if (($this->balances[$this->selectItem->id] ?? 0) !== 0) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'مانده این حساب صفر نیست؛ ابتدا موجودی را به حساب دیگری منتقل کنید.');
            return;
        }

        $this->selectItem->delete();
        $this->loadData();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'حساب حذف شد.');
        $this->resetData('close');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $info['header'] }}</h1>
            <div class="text-muted small">پرداخت‌های سایت بر اساس کارت / درگاه متصل، و بقیه به حساب پیش‌فرض ثبت می‌شوند.</div>
        </div>
        <div class="btn-list">
            @can('accounting.create')
                <button wire:click="resetData()" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave">
                    <i class="ri-add-line align-middle"></i> {{ $info['create'] }}
                </button>
            @endcan
        </div>
    </div>

    @include('pages.dashboard.accounting.partials.nav', ['active' => 'accounting.accounts'])

    <div class="row">
        <div class="col-md-4">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="text-muted small mb-1">جمع موجودی حساب‌های فعال</div>
                    @php($totalBalance = collect($data)->where('status', true)->sum(fn ($a) => $balances[$a->id] ?? 0))
                    <div class="fs-20 fw-bold {{ $totalBalance < 0 ? 'text-danger' : '' }}">{{ number_format($totalBalance) }} <small class="fs-12 text-muted">تومان</small></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-header"><div class="card-title">{{ $info['header'] }}</div></div>
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
                        <tr wire:key="account-{{ $item->id }}">
                            <th>{{ $loop->iteration }}</th>
                            <td>
                                <div class="fw-semibold">
                                    {{ $item->title }}
                                    @if($item->is_default)
                                        <span class="badge bg-primary-transparent ms-1">پیش‌فرض</span>
                                    @endif
                                </div>
                                @if($item->account_number)
                                    <small class="text-muted" dir="ltr">{{ $item->account_number }}</small>
                                @endif
                            </td>
                            <td>{{ $item->type_label }}</td>
                            <td class="small">
                                @if($item->bankCard)
                                    <div><i class="ri-bank-card-line me-1"></i>کارت {{ $item->bankCard->bank_name }} <span dir="ltr">{{ $item->bankCard->masked_number }}</span></div>
                                @endif
                                @if($item->gateway)
                                    <div><i class="ri-secure-payment-line me-1"></i>درگاه {{ $item->gateway->title }}</div>
                                @endif
                                @if(!$item->bankCard && !$item->gateway)
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="fw-bold {{ $balance < 0 ? 'text-danger' : '' }}">{{ number_format($balance) }}</td>
                            <td>
                                @can('accounting.edit')
                                    <span style="cursor: pointer" wire:click="change_status({{ $item->id }})"
                                          class="badge bg-outline-{{ $item->status ? 'success' : 'danger' }}">{{ $item->status ? 'فعال' : 'غیرفعال' }}</span>
                                @else
                                    <span class="badge bg-outline-{{ $item->status ? 'success' : 'danger' }}">{{ $item->status ? 'فعال' : 'غیرفعال' }}</span>
                                @endcan
                            </td>
                            <td>
                                <div class="hstack gap-2">
                                    <a href="{{ route('accounting.entries', ['account' => $item->id]) }}" class="text-primary fs-14 lh-1" title="گردش حساب"><i class="ri-file-list-3-line"></i></a>
                                    @can('accounting.edit')
                                        <a data-bs-toggle="modal" href="#create" wire:click="get_data({{ $item->id }})" class="text-info fs-14 lh-1"><i class="ri-edit-line"></i></a>
                                    @endcan
                                    @can('accounting.delete')
                                        <a data-bs-toggle="modal" href="#delete" wire:click="get_data({{ $item->id }})" class="text-danger fs-14 lh-1"><i class="ri-delete-bin-5-line"></i></a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($info['table']['headers']) }}" class="text-center py-5 text-muted">
                                <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                <strong>حسابی ثبت نشده است.</strong>
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
                    <h6 class="modal-title">{{ $selectItem ? 'ویرایش حساب' : $info['create'] }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="save" id="save-account">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">عنوان</label>
                                <input wire:model="title" type="text" class="form-control @error('title') is-invalid @enderror" placeholder="مثلاً: بانک ملت - حساب جاری">
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">نوع</label>
                                <select wire:model="type" class="form-select @error('type') is-invalid @enderror">
                                    @foreach(\App\Models\AccountingAccount::TYPES as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">شماره حساب / شبا</label>
                                <input wire:model="account_number" type="text" dir="ltr" class="form-control @error('account_number') is-invalid @enderror">
                                @error('account_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">کارت کارت‌به‌کارت متصل</label>
                                <select wire:model="bank_card_id" class="form-select @error('bank_card_id') is-invalid @enderror">
                                    <option value="">— بدون اتصال —</option>
                                    @foreach($this->bankCards() as $card)
                                        <option value="{{ $card->id }}">{{ $card->bank_name }} - {{ $card->owner_name }} ({{ $card->masked_number }})</option>
                                    @endforeach
                                </select>
                                @error('bank_card_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">پرداخت‌های تأییدشده کارت‌به‌کارت به این کارت، به این حساب ثبت می‌شوند.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">درگاه پرداخت متصل</label>
                                <select wire:model="payment_gateway_id" class="form-select @error('payment_gateway_id') is-invalid @enderror">
                                    <option value="">— بدون اتصال —</option>
                                    @foreach($this->gateways() as $gateway)
                                        <option value="{{ $gateway->id }}">{{ $gateway->title }}</option>
                                    @endforeach
                                </select>
                                @error('payment_gateway_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">تسویه این درگاه به این حساب واریز می‌شود.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">مانده اول دوره (تومان)</label>
                                <input wire:model="opening_balance" type="number" class="form-control @error('opening_balance') is-invalid @enderror">
                                @error('opening_balance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">موجودی حساب در روز شروع استفاده از حسابداری.</div>
                            </div>
                            <div class="col-md-6 d-flex flex-column justify-content-center gap-2">
                                <div class="form-check form-switch">
                                    <input wire:model="is_default" class="form-check-input" type="checkbox" id="is_default">
                                    <label class="form-check-label" for="is_default">حساب پیش‌فرض (فروش حضوری و پرداخت‌های بدون حساب مشخص)</label>
                                </div>
                                @error('is_default') <div class="text-danger small">{{ $message }}</div> @enderror
                                <div class="form-check form-switch">
                                    <input wire:model="status" class="form-check-input" type="checkbox" id="account_status">
                                    <label class="form-check-label" for="account_status">فعال</label>
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
                        <button type="submit" form="save-account" class="btn btn-primary">ذخیره</button>
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
                    <h6 class="modal-title">{{ $info['delete'] }} {{ $selectItem?->title }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger mb-0">از حذف این حساب مطمئن هستید؟ فقط حساب با مانده صفر قابل حذف است.</div>
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
</div>
