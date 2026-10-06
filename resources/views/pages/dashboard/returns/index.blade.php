<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\ReturnRequest;
use App\Services\Returns\ReturnRequestService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';

    public $info = [];
    public string $search = '';
    public string $status = '';

    public ?int $selectedId = null;
    public string $newStatus = '';
    public ?string $adminNote = null;
    public ?int $refundAmount = null;

    public ReturnRequest $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(ReturnRequest $model)
    {
        // مرجوعی بخش مالی است و از مجوزهای فاکتور استفاده می‌کند
        abort_if(!auth()->user()->can('returns.view'), 403);

        $this->model = $model;
        $this->info['header'] = 'درخواست‌های مرجوعی';
        $this->info['table']['headers'] = [
            '#',
            'شماره مرجوعی',
            'سفارش',
            'کاربر',
            'دلیل',
            'مبلغ',
            'تاریخ',
            'وضعیت',
            'عملیات',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function data()
    {
        return $this->model
            ->newQuery()
            ->with(['order:id,order_number', 'user:id,first_name,last_name,mobile'])
            ->withCount('items')
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);

                $query->where(function ($q) use ($term) {
                    $q->where('return_number', 'like', "%{$term}%")
                        ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%"))
                        ->orWhereHas('user', fn ($u) => $u->where('mobile', 'like', "%{$term}%")
                            ->orWhere('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%"));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            // درخواست‌های در انتظار اول
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function selected(): ?ReturnRequest
    {
        return $this->selectedId
            ? $this->model->with(['order', 'user', 'items.orderItem', 'transactions'])->find($this->selectedId)
            : null;
    }

    #[Computed]
    public function allowedStatuses(): array
    {
        return $this->selected
            ? ReturnRequestService::TRANSITIONS[$this->selected->status] ?? []
            : [];
    }

    #[Computed]
    public function maxRefundable(): int
    {
        return $this->selected ? app(ReturnRequestService::class)->maxRefundable($this->selected) : 0;
    }

    public function get_data($id)
    {
        abort_if(!auth()->user()->can('returns.view'), 403);

        $this->resetErrorBag();
        $this->selectedId = $this->model->findOrFail($id)->id;
        unset($this->selected, $this->allowedStatuses, $this->maxRefundable);

        $this->newStatus = $this->allowedStatuses[0] ?? '';
        $this->adminNote = $this->selected->admin_note;
        $this->refundAmount = min($this->selected->refund_amount, $this->maxRefundable);
    }

    public function rules()
    {
        return [
            'newStatus' => ['required', Rule::in($this->allowedStatuses)],
            'adminNote' => [Rule::requiredIf($this->newStatus === 'rejected'), 'nullable', 'string', 'max:1000'],
            'refundAmount' => [Rule::requiredIf($this->newStatus === 'refunded'), 'nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'newStatus.required' => 'وضعیت جدید را انتخاب کنید.',
            'newStatus.in' => 'وضعیت انتخاب‌شده برای این درخواست مجاز نیست.',
            'adminNote.required' => 'برای رد درخواست، دلیل را برای کاربر بنویسید.',
            'adminNote.max' => 'توضیحات نمی‌تواند بیشتر از ۱۰۰۰ کاراکتر باشد.',
            'refundAmount.required' => 'مبلغ استرداد را وارد کنید.',
            'refundAmount.integer' => 'مبلغ استرداد باید عدد باشد.',
            'refundAmount.min' => 'مبلغ استرداد باید بیشتر از صفر باشد.',
        ];
    }

    public function save()
    {
        abort_if(!auth()->user()->can('returns.edit'), 403);

        if (!$this->selected) {
            return;
        }

        $this->validate();

        try {
            $request = app(ReturnRequestService::class)->changeStatus(
                $this->selectedId,
                $this->newStatus,
                filled($this->adminNote) ? trim($this->adminNote) : null,
                $this->newStatus === 'refunded' ? (int) $this->refundAmount : null
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator?->errors() ?? $e->errors());
            return;
        }

        unset($this->data);

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $request->status === 'refunded'
                ? 'مبلغ ' . number_format($request->refund_amount) . ' تومان به کیف پول کاربر واریز شد.'
                : 'وضعیت درخواست به «' . $request->status_label . '» تغییر کرد.'
        );

        $this->resetData('close');
    }

    public function resetData($action = 'create')
    {
        $this->reset(['selectedId', 'newStatus', 'adminNote', 'refundAmount']);
        $this->resetErrorBag();

        if ($action !== 'create') {
            $this->dispatch('close-modal');
        }
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">
                {{ $info['header'] }}
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header pb-0 justify-content-between">
                    <div class="card-title">
                        {{ $info['header'] }}
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <select wire:model.live="status" class="form-select form-select-sm" style="width: 180px">
                            <option value="">همه وضعیت‌ها</option>
                            @foreach(\App\Models\ReturnRequest::STATUSES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="header-element header-search d-md-block d-none my-auto">
                            <div class="autoComplete_wrapper" role="combobox" aria-haspopup="true" aria-expanded="false"><input wire:model.lazy="search" autocapitalize="none" autocomplete="off" class="header-search-bar form-control" placeholder="شماره مرجوعی، سفارش یا موبایل..." spellcheck="false" type="text"></div>
                            <a class="header-search-icon border-0" href="javascript:void(0);">
                                <i class="bi bi-search"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap">
                            <thead>
                            <tr>
                                @foreach($info['table']['headers'] ?? [] as $h)
                                    <th scope="col">{{ $h }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($this->data as $item)
                                <tr wire:key="{{ $item->id }}">
                                    <th scope="row">{{ $this->data->firstItem() + $loop->index }}</th>
                                    <td class="fw-semibold">{{ $item->return_number }}</td>
                                    <td>{{ $item->order?->order_number ?? '-' }}</td>
                                    <td>
                                        {{ $item->user?->mobile }}
                                        {{ trim((string) $item->user?->full_name) ? ' - ' . $item->user->full_name : '' }}
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($item->reason_label, 30) }}</td>
                                    <td>{{ number_format($item->refund_amount) }} <small class="text-muted">تومان</small></td>
                                    <td>{{ verta($item->created_at)->format('Y/m/d H:i') }}</td>
                                    <td>
                                        <span class="badge bg-outline-{{ $item->status_badge }}">{{ $item->status_label }}</span>
                                    </td>
                                    <td>
                                        <a data-bs-toggle="modal" href="#create" wire:click="get_data({{ $item->id }})" class="text-info fs-14 lh-1" title="بررسی">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($info['table']['headers'] ?? []) }}" class="text-center py-5 text-muted">
                                        <i class="ri-arrow-go-back-line fs-1 d-block mb-2"></i>
                                        <strong>درخواست مرجوعی‌ای وجود ندارد.</strong>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="100">
                                        {{ $this->data?->links() }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content modal-content-demo">

                {{-- ثبت فقط با دکمه (تا تأیید استرداد با Enter دور زده نشود) --}}
                <form x-on:submit.prevent>

                    <div class="modal-header">
                        <h6 class="modal-title">
                            بررسی درخواست مرجوعی {{ $this->selected?->return_number }}
                        </h6>
                        <button aria-label="Close" class="btn-close" type="button" data-bs-dismiss="modal" wire:click="resetData"></button>
                    </div>

                    <div class="modal-body text-start">
                        @if($request = $this->selected)
                            <div class="row g-3 mb-4">
                                <div class="col-md-3"><small class="text-muted d-block">سفارش</small><strong>{{ $request->order?->order_number }}</strong></div>
                                <div class="col-md-3"><small class="text-muted d-block">کاربر</small><strong>{{ $request->user?->mobile }}</strong> {{ $request->user?->full_name }}</div>
                                <div class="col-md-3"><small class="text-muted d-block">مبلغ کل سفارش</small><strong>{{ number_format($request->order?->total_amount) }}</strong> تومان</div>
                                <div class="col-md-3"><small class="text-muted d-block">وضعیت فعلی</small><span class="badge bg-{{ $request->status_badge }}">{{ $request->status_label }}</span></div>
                                <div class="col-md-12"><small class="text-muted d-block">دلیل</small>{{ $request->reason_label }}</div>
                                @if(filled($request->description))
                                    <div class="col-md-12"><small class="text-muted d-block">توضیحات کاربر</small><div style="white-space: pre-line">{{ $request->description }}</div></div>
                                @endif
                            </div>

                            <div class="table-responsive mb-4">
                                <table class="table table-bordered text-nowrap mb-0">
                                    <thead>
                                    <tr><th>کالا</th><th>تعداد مرجوعی</th><th>قیمت واحد</th><th>مبلغ</th></tr>
                                    </thead>
                                    <tbody>
                                    @foreach($request->items as $returnItem)
                                        <tr wire:key="ri-{{ $returnItem->id }}">
                                            <td>{{ $returnItem->orderItem?->product_name ?? '-' }}</td>
                                            <td>{{ $returnItem->quantity }} از {{ $returnItem->orderItem?->quantity }}</td>
                                            <td>{{ number_format($returnItem->orderItem?->price) }}</td>
                                            <td>{{ number_format($returnItem->amount) }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if($request->status === 'refunded')
                                <div class="alert alert-success">
                                    مبلغ {{ number_format($request->refund_amount) }} تومان در تاریخ {{ verta($request->refunded_at)->format('Y/m/d H:i') }} به کیف پول کاربر واریز شد.
                                </div>
                            @endif

                            @if(!empty($this->allowedStatuses))
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">تغییر وضعیت به</label>
                                        <select wire:model.live="newStatus" class="form-select @error('newStatus') is-invalid @enderror">
                                            @foreach($this->allowedStatuses as $key)
                                                <option value="{{ $key }}">{{ \App\Models\ReturnRequest::STATUSES[$key] }}</option>
                                            @endforeach
                                        </select>
                                        @error('newStatus')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    @if($newStatus === 'refunded')
                                        <div class="col-md-4">
                                            <label class="form-label">مبلغ استرداد به کیف پول (تومان)</label>
                                            <input type="number" min="1" max="{{ $this->maxRefundable }}" wire:model="refundAmount" class="form-control @error('refundAmount') is-invalid @enderror">
                                            <div class="form-text">حداکثر {{ number_format($this->maxRefundable) }} تومان</div>
                                            @error('refundAmount')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endif

                                    <div class="col-md-12">
                                        <label class="form-label">پیام برای کاربر {{ $newStatus === 'rejected' ? '(الزامی)' : '(اختیاری)' }}</label>
                                        <textarea wire:model="adminNote" rows="3" class="form-control @error('adminNote') is-invalid @enderror" placeholder="این پیام در جزئیات مرجوعی به کاربر نمایش داده می‌شود"></textarea>
                                        @error('adminNote')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-secondary mb-0">این درخواست در وضعیت نهایی است و قابل تغییر نیست.</div>
                            @endif
                        @endif
                    </div>

                    <div class="modal-footer">
                        <div wire:loading.remove wire:target="save">
                            @if(!empty($this->allowedStatuses))
                                @can('returns.edit')
                                    <button type="button" class="btn btn-primary"
                                            wire:click="save"
                                            @if($newStatus === 'refunded') wire:confirm="مبلغ استرداد به کیف پول کاربر واریز می‌شود. ادامه می‌دهید؟" @endif>
                                        ثبت وضعیت
                                    </button>
                                @endcan
                            @endif
                            <button class="btn btn-light" data-bs-dismiss="modal" type="button" wire:click="resetData">بستن</button>
                        </div>

                        <div wire:loading wire:target="save" class="spinner-grow text-info" role="status">
                            <span class="visually-hidden">در حال ثبت...</span>
                        </div>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>
