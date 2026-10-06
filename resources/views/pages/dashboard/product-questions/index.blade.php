<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use \App\Models\ProductQuestion;
use \App\Models\ProductAnswer;
new class extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';

    public $info=[];
    public $selectItem;
    public $body;
    public $answer;
    public $search;
    public $status = '';

    public ProductQuestion $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(ProductQuestion $model)
    {
        abort_if(!auth()->user()->can('product-questions.view'), 403);

        $this->model=$model;
        $this->info['header']='پرسش و پاسخ محصولات';
        $this->info['create']='مدیریت پرسش';
        $this->info['delete']='حذف پرسش';
        $this->info['personal']='پرسش';
        $this->info['table']['headers']=[
            '#',
            'محصول',
            'کاربر',
            'پرسش',
            'پاسخ‌ها',
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
            ->with(['product:id,title,slug', 'user'])
            ->withCount([
                'answers',
                'answers as pending_answers_count' => fn ($q) => $q->where('status', false),
            ])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('body', 'like', "%{$this->search}%")
                        ->orWhereHas('product', fn ($p) => $p->where('title', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', (bool) $this->status))
            ->orderBy('status')
            ->latest('id')
            ->paginate(20);
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('product-questions.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $item->status
                ? 'پرسش با موفقیت تایید شد.'
                : 'تایید پرسش لغو شد.'
        );
    }

    public function change_answer_status($id)
    {
        abort_if(!auth()->user()->can('product-questions.edit'), 403);

        $item = ProductAnswer::where('product_question_id', $this->selectItem?->id)->findOrFail($id);
        $item->update(['status' => !$item->status]);

        $this->refreshSelectItem();

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $item->status
                ? 'پاسخ با موفقیت تایید شد.'
                : 'تایید پاسخ لغو شد.'
        );
    }

    public function delete_answer($id)
    {
        abort_if(!auth()->user()->can('product-questions.delete'), 403);

        ProductAnswer::where('product_question_id', $this->selectItem?->id)->findOrFail($id)->delete();

        $this->refreshSelectItem();

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'پاسخ با موفقیت حذف شد.'
        );
    }

    public function delete()
    {
        abort_if(!auth()->user()->can('product-questions.delete'), 403);

        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();

            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: $this->info['personal']." باموفقیت حذف شد."
            );

            $this->resetData('close');
        }
    }

    public function get_data($id)
    {
        $this->resetErrorBag();
        $this->selectItem = $this->model->with(['product:id,title,slug', 'user', 'answers.user'])->findOrFail($id);
        $this->body = $this->selectItem->body;
        $this->answer = null;
    }

    private function refreshSelectItem(): void
    {
        $this->selectItem = $this->model->with(['product:id,title,slug', 'user', 'answers.user'])->findOrFail($this->selectItem->id);
    }

    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','search','status');
        }else{
            $this->resetExcept(['model','info','search','status']);
            $this->dispatch('close-modal');
        }
    }

    public function rules()
    {
        return [
            'body' => ['required', 'string', 'min:10', 'max:1000'],
            'answer' => ['nullable', 'string', 'min:2', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'متن پرسش الزامی است.',
            'body.string' => 'متن پرسش باید متن باشد.',
            'body.min' => 'متن پرسش باید حداقل ۱۰ کاراکتر باشد.',
            'body.max' => 'متن پرسش نمی‌تواند بیشتر از ۱۰۰۰ کاراکتر باشد.',
            'answer.string' => 'پاسخ باید متن باشد.',
            'answer.min' => 'پاسخ باید حداقل ۲ کاراکتر باشد.',
            'answer.max' => 'پاسخ نمی‌تواند بیشتر از ۲۰۰۰ کاراکتر باشد.',
        ];
    }

    public function save()
    {
        abort_if(!auth()->user()->can('product-questions.edit'), 403);

        $this->validate();

        if (!$this->selectItem) {
            return;
        }

        $item = $this->model->findOrFail($this->selectItem->id);

        // ثبت پاسخ کارشناس باعث تایید خودکار پرسش می‌شود
        $item->update([
            'body' => trim($this->body),
            'status' => filled($this->answer) ? true : $item->status,
        ]);

        if (filled($this->answer)) {
            $item->answers()->create([
                'user_id' => auth()->id(),
                'body' => trim($this->answer),
                'is_official' => true,
                'status' => true,
            ]);
        }

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: filled($this->answer)
                ? 'پاسخ با موفقیت ثبت شد.'
                : $this->info['personal'].' باموفقیت ویرایش شد.'
        );

        $this->resetData('close');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">
                {{$info['header']}}
            </h1>
        </div>
        <div class="btn-list">
            @can('product-questions.view')
                <a href="{{route('product-questions.trash')}}" class="btn btn-warning-light btn-wave me-2">
                    <i class="bx bx-trash align-middle">
                    </i>
                    سطل آشغال
                </a>
            @endcan
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header pb-0 justify-content-between">
                    <div class="card-title">
                        {{$info['header']}}
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <select wire:model.live="status" class="form-select form-select-sm" style="width: 160px">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="0">در انتظار تایید</option>
                            <option value="1">تایید شده</option>
                        </select>
                        <div class="header-element header-search d-md-block d-none my-auto">
                            <div class="autoComplete_wrapper" role="combobox" aria-owns="autoComplete_list_1" aria-haspopup="true" aria-expanded="false"><input wire:model.lazy="search" autocapitalize="none" autocomplete="off" class="header-search-bar form-control" id="header-search" placeholder="جستجو در پرسش یا نام محصول..." spellcheck="false" type="text" aria-controls="autoComplete_list_1" aria-autocomplete="both"><ul id="autoComplete_list_1" role="listbox" hidden=""></ul></div>
                            <a class="header-search-icon border-0" href="javascript:void(0);">
                                <i class="bi bi-search">
                                </i>
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
                                    <th scope="col">
                                        {{$h}}
                                    </th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($this->data ?? [] as $item)
                                <tr wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{ $this->data->firstItem() + $loop->index }}
                                    </th>
                                    <td>
                                        @if($item->product)
                                            <a href="{{ route('products.show', $item->product->slug) }}" target="_blank">
                                                {{ \Illuminate\Support\Str::limit($item->product->title, 30) }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->user)
                                            {{$item->user->mobile}} {{ trim($item->user->full_name) ? ' - '. $item->user->full_name : '' }}
                                        @else
                                            -
                                        @endif
                                        @if($item->is_anonymous)
                                            <span class="badge bg-light text-dark ms-1">ناشناس</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ \Illuminate\Support\Str::limit($item->body, 50) }}
                                    </td>
                                    <td>
                                        <span class="badge bg-outline-info">{{ $item->answers_count }}</span>
                                        @if($item->pending_answers_count)
                                            <span class="badge bg-outline-warning">{{ $item->pending_answers_count }} در انتظار</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span
                                            style="cursor: pointer"
                                            wire:click="change_status({{ $item->id }})"
                                            wire:loading.attr="disabled"
                                            class="badge bg-outline-{{ $item->status ? 'success' : 'warning' }}">

                                            <span wire:target="change_status({{ $item->id }})" wire:loading.remove>
                                                {{ $item->status ? 'تایید شده' : 'در انتظار تایید' }}
                                            </span>

                                            <span wire:target="change_status({{ $item->id }})" wire:loading>
                                                در حال تغییر...
                                            </span>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="hstack gap-2 flex-wrap">
                                            @can('product-questions.edit')
                                                <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})" class="text-info fs-14 lh-1"><i
                                                        class="ri-edit-line"></i></a>
                                            @endcan
                                            @can('product-questions.delete')
                                                <a data-bs-toggle="modal" href="#delete" wire:click="get_data({{$item->id}})" class="text-danger fs-14 lh-1"><i
                                                        class="ri-delete-bin-5-line"></i></a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($info['table']['headers'] ?? []) }}" class="text-center py-5 text-muted">
                                        <i class="ri-question-answer-line fs-1 d-block mb-2"></i>
                                        <strong>پرسشی برای نمایش وجود ندارد.</strong>
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
        <div class="modal-dialog modal-dialog-centered text-center modal-xl" role="document">
            <div class="modal-content modal-content-demo">

                <form wire:submit="save()">

                    <div class="modal-header">
                        <h6 class="modal-title">
                            {{$info['create']}}
                            @if($selectItem?->product)
                                - {{ $selectItem->product->title }}
                            @endif
                        </h6>

                        <button aria-label="Close"
                                class="btn-close"
                                type="button"
                                data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body text-start">

                        <div class="row">

                            <div class="col-xl-12 mt-3">
                                <label class="form-label">
                                    متن پرسش
                                </label>

                                <textarea
                                    wire:model.lazy="body"
                                    class="form-control @error('body') is-invalid @enderror"
                                    rows="3"
                                    placeholder="متن پرسش"></textarea>

                                @error('body')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            @if($selectItem && $selectItem->answers->isNotEmpty())
                                <div class="col-xl-12 mt-4">
                                    <label class="form-label">
                                        پاسخ‌های ثبت‌شده
                                    </label>

                                    <ul class="list-group">
                                        @foreach($selectItem->answers as $ans)
                                            <li wire:key="answer-{{ $ans->id }}" class="list-group-item d-flex justify-content-between align-items-start gap-3">
                                                <div class="text-wrap">
                                                    <div class="fw-semibold mb-1">
                                                        {{ $ans->user ? ($ans->user->mobile . (trim($ans->user->full_name) ? ' - '.$ans->user->full_name : '')) : '-' }}
                                                        @if($ans->is_official)
                                                            <span class="badge bg-success-transparent ms-1">پاسخ کارشناس</span>
                                                        @endif
                                                        <small class="text-muted ms-1">{{ verta($ans->created_at)->format('Y/m/d H:i') }}</small>
                                                    </div>
                                                    <div style="white-space: pre-line">{{ $ans->body }}</div>
                                                </div>
                                                <div class="hstack gap-2 flex-shrink-0">
                                                    <span
                                                        style="cursor: pointer"
                                                        wire:click="change_answer_status({{ $ans->id }})"
                                                        class="badge bg-outline-{{ $ans->status ? 'success' : 'warning' }}">
                                                        {{ $ans->status ? 'تایید شده' : 'در انتظار تایید' }}
                                                    </span>
                                                    @can('product-questions.delete')
                                                        <a href="javascript:void(0);"
                                                           wire:click="delete_answer({{ $ans->id }})"
                                                           wire:confirm="از حذف این پاسخ مطمئن هستید؟"
                                                           class="text-danger fs-14 lh-1"><i class="ri-delete-bin-5-line"></i></a>
                                                    @endcan
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="col-xl-12 mt-3">
                                <label class="form-label">
                                    پاسخ جدید کارشناس
                                </label>

                                <textarea
                                    wire:model.lazy="answer"
                                    class="form-control @error('answer') is-invalid @enderror"
                                    rows="3"
                                    placeholder="در صورت نیاز پاسخ کارشناس را وارد کنید (با ثبت پاسخ، پرسش نیز تایید می‌شود)"></textarea>

                                @error('answer')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <div wire:loading.remove wire:target="save">
                            <button
                                type="submit"
                                class="btn btn-primary">
                                ذخیره تغییرات
                            </button>

                            <button
                                class="btn btn-light"
                                data-bs-dismiss="modal"
                                type="button">
                                بستن
                            </button>
                        </div>

                        <div wire:loading
                             wire:target="save"
                             class="spinner-grow text-info"
                             role="status">
                            <span class="visually-hidden">
                                در حال بارگیری...
                            </span>
                        </div>

                    </div>

                </form>

            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered text-center " role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{$info['delete']}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="ri-error-warning-line fs-18 me-2"></i>
                        <div>
                            از حذف کردن این ایتم مطمین هستید ؟!
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="delete">
                        <button class="btn btn-primary" wire:click="delete()">
                            حذف
                        </button>
                        <button class="btn btn-light" data-bs-dismiss="modal">
                            بستن
                        </button>
                    </div>

                    <div wire:loading wire:target="delete" class="spinner-grow text-info" role="status">
                        <span class="visually-hidden">در حال حذف...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
