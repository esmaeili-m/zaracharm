<?php

use Livewire\Component;
use App\Models\AccountingCategory;
use Illuminate\Validation\Rule;

new class extends Component
{
    public $info = [];
    public $data;
    public $selectItem;
    public AccountingCategory $model;

    public $type = 'expense';
    public $title;
    public $affects_profit = true;
    public $status = true;
    public $filterType = '';

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(AccountingCategory $model)
    {
        abort_if(!auth()->user()->can('accounting.view'), 403);
        $this->model = $model;
        $this->info['header'] = 'دسته‌بندی هزینه و درآمد';
        $this->info['create'] = 'افزودن دسته';
        $this->info['delete'] = 'حذف دسته';
        $this->info['personal'] = 'دسته';
        $this->info['table']['headers'] = ['#', 'عنوان', 'نوع', 'در سود و زیان', 'تعداد ثبت', 'وضعیت', 'عملیات'];
        $this->loadData();
    }

    public function updatedFilterType()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->data = $this->model->newQuery()
            ->withCount('entries')
            ->when($this->filterType, fn ($q) => $q->where('type', $this->filterType))
            ->orderBy('type')
            ->orderBy('sort')
            ->get();
    }

    public function get_data($id)
    {
        $this->selectItem = $this->model->findOrFail($id);
        $this->type = $this->selectItem->type;
        $this->title = $this->selectItem->title;
        $this->affects_profit = (bool) $this->selectItem->affects_profit;
        $this->status = (bool) $this->selectItem->status;
        $this->resetValidation();
    }

    public function resetData($action = 'create')
    {
        if ($action == 'create') {
            $this->resetExcept('model', 'info', 'data', 'filterType');
        } else {
            $this->resetExcept(['selectItem', 'model', 'info', 'data', 'filterType']);
            $this->dispatch('close-modal');
        }
        $this->resetValidation();
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('accounting.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);
        $this->loadData();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'وضعیت دسته بروز شد.');
    }

    public function rules()
    {
        return [
            'type' => ['required', Rule::in(array_keys(AccountingCategory::TYPES))],
            'title' => ['required', 'string', 'min:2', 'max:150'],
            'affects_profit' => ['boolean'],
            'status' => ['boolean'],
        ];
    }

    public function messages()
    {
        return [
            'type.required' => 'نوع دسته را انتخاب کنید.',
            'type.in' => 'نوع دسته معتبر نیست.',
            'title.required' => 'عنوان دسته الزامی است.',
            'title.min' => 'عنوان دسته باید حداقل ۲ کاراکتر باشد.',
            'title.max' => 'عنوان دسته نباید بیشتر از ۱۵۰ کاراکتر باشد.',
        ];
    }

    public function save()
    {
        abort_if(!auth()->user()->can($this->selectItem ? 'accounting.edit' : 'accounting.create'), 403);

        $data = $this->validate();

        $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data + ['sort' => (int) $this->model->max('sort') + 1]);

        $this->loadData();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق',
            text: $this->selectItem ? 'دسته ویرایش شد.' : 'دسته جدید ایجاد شد.');
        $this->resetData('close');
    }

    public function delete()
    {
        abort_if(!auth()->user()->can('accounting.delete'), 403);

        if ($this->selectItem) {
            // ردیف‌های قبلی دسته را با withTrashed نگه می‌دارند
            $this->selectItem->delete();
            $this->loadData();
            $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'دسته حذف شد.');
            $this->resetData('close');
        }
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $info['header'] }}</h1>
            <div class="text-muted small">دسته‌هایی که «در سود و زیان» ندارند (مثل آورده یا برداشت مالک) فقط موجودی حساب را تغییر می‌دهند.</div>
        </div>
        <div class="btn-list">
            @can('accounting.create')
                <button wire:click="resetData()" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave">
                    <i class="ri-add-line align-middle"></i> {{ $info['create'] }}
                </button>
            @endcan
        </div>
    </div>

    @include('pages.dashboard.accounting.partials.nav', ['active' => 'accounting.categories'])

    <div class="card custom-card">
        <div class="card-header justify-content-between">
            <div class="card-title">{{ $info['header'] }}</div>
            <select wire:model.live="filterType" class="form-select form-select-sm w-auto">
                <option value="">همه</option>
                @foreach(\App\Models\AccountingCategory::TYPES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
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
                        <tr wire:key="category-{{ $item->id }}">
                            <th>{{ $loop->iteration }}</th>
                            <td class="fw-semibold">{{ $item->title }}</td>
                            <td><span class="badge bg-{{ $item->type === 'expense' ? 'danger' : 'success' }}-transparent">{{ $item->type_label }}</span></td>
                            <td>
                                @if($item->affects_profit)
                                    <i class="ri-checkbox-circle-line text-success fs-16"></i>
                                @else
                                    <span class="text-muted small">خیر</span>
                                @endif
                            </td>
                            <td>{{ number_format($item->entries_count) }}</td>
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
                                <strong>دسته‌ای ثبت نشده است.</strong>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $selectItem ? 'ویرایش دسته' : $info['create'] }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="save" id="save-category">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">نوع</label>
                                <select wire:model="type" class="form-select @error('type') is-invalid @enderror">
                                    @foreach(\App\Models\AccountingCategory::TYPES as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">عنوان</label>
                                <input wire:model="title" type="text" class="form-control @error('title') is-invalid @enderror" placeholder="مثلاً: اجاره مغازه">
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input wire:model="affects_profit" class="form-check-input" type="checkbox" id="affects_profit">
                                    <label class="form-check-label" for="affects_profit">در گزارش سود و زیان حساب شود</label>
                                </div>
                                <div class="form-text">برای آورده سرمایه، برداشت مالک یا تسویه مارکت‌پلیس (که فروشش قبلاً حساب شده) خاموش کنید.</div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input wire:model="status" class="form-check-input" type="checkbox" id="category_status">
                                    <label class="form-check-label" for="category_status">فعال</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="save">
                        <button type="submit" form="save-category" class="btn btn-primary">ذخیره</button>
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
                    <div class="alert alert-danger mb-0">
                        از حذف این دسته مطمئن هستید؟ ثبت‌های قبلی این دسته حذف نمی‌شوند.
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
</div>
