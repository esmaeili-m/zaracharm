<?php

use Livewire\Component;
use \App\Models\Invoice;
use \App\Models\Tag;
use \App\Services\FileUploadService;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;

    public $search;
    public Invoice $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Invoice $model)
    {
        abort_if(!auth()->user()->can('invoices.view'), 403);

        $this->model=$model;
        $this->info['header']='لیست فاکتورها';
        $this->info['create']='افزودن فاکتور';
        $this->info['delete']='حذف فاکتور';
        $this->info['personal']='فاکتور';
        $this->info['table']['headers'] = [
            '#',
            'شماره فاکتور',
            'کاربر',
            'وضعیت',
            'مبلغ کل',
            'مبلغ پرداختی',
            'عملیات',
        ];
        $this->loadData();
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('invoices.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);
        $this->loadData();
    }

    public function loadData()
    {
        $query = $this->model
            ->where(function ($query) {

                $query->where('invoice_number', 'LIKE', '%' . $this->search . '%')
                    ->orWhere('total_amount', 'LIKE', '%' . $this->search . '%')
                    ->orWhere('paid_amount', 'LIKE', '%' . $this->search . '%');
            });

        $this->data = $query->get();
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

    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header pb-0 justify-content-between">
                    <div class="card-title">
                        {{$info['header']}}
                    </div>
                    <div class="header-element header-search d-md-block d-none my-auto">
                        <div class="autoComplete_wrapper" role="combobox" aria-owns="autoComplete_list_1" aria-haspopup="true" aria-expanded="false"><input wire:model.lazy="search" autocapitalize="none" autocomplete="off" class="header-search-bar form-control" id="header-search" placeholder="جستجو برای نتایج..." spellcheck="false" type="text" aria-controls="autoComplete_list_1" aria-autocomplete="both"><ul id="autoComplete_list_1" role="listbox" hidden=""></ul></div>
                        <a class="header-search-icon border-0" href="javascript:void(0);">
                            <i wire:click="loadData()" class="bi bi-search">
                            </i>
                        </a>
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

                            @php($counter = 1)

                            @foreach($data ?? [] as $item)

                                <tr wire:key="invoice-{{ $item->id }}">

                                    {{-- # --}}
                                    <th scope="row">
                                        {{ $counter }}
                                    </th>

                                    {{-- شماره فاکتور --}}
                                    <td>
                                        {{ $item->invoice_number }}
                                    </td>
                                    <td>
                                        {{ $item->user->mobile ?? '' }} {{' - '.$item->user->name}}
                                    </td>

                                    {{-- وضعیت --}}
                                    <td>
                    <span class="badge bg-{{ $item->status == 'paid' ? 'success' : ($item->status == 'pending' ? 'warning' : 'danger') }}">
                        {{ $item->status == 'paid' ? 'پرداخت شده' : ($item->status == 'pending' ? 'در انتظار' : 'ناموفق') }}
                    </span>
                                    </td>

                                    {{-- مبلغ کل --}}
                                    <td>
                                        {{ number_format($item->total_amount) }} تومان
                                    </td>

                                    {{-- مبلغ پرداختی --}}
                                    <td>
                                        {{ number_format($item->paid_amount ?? 0) }} تومان
                                    </td>

                                    {{-- عملیات --}}
                                    <td>
                                        <div class="hstack gap-2 flex-wrap">

                                            {{-- مشاهده --}}
                                            <a href="{{ route('invoices.details', $item->id) }}"
                                               class="text-info fs-14 lh-1">
                                                <i class="ri-eye-line"></i>
                                            </a>

                                        </div>
                                    </td>

                                </tr>

                                @php($counter++)
                            @endforeach

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered text-center modal-xl" role="document">
            <div class="modal-content modal-content-demo">
                <form wire:submit.prevent="save()">
                    <div class="modal-header">
                        <h6 class="modal-title">{{$info['create']}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                        <div class="row">
                            <div class="col-xl-4">
                                <label  for="input-rounded" class="form-label ">نام {{$info['personal'] ?? ''}}</label>
                                <input wire:model.lazy="name" type="text" class="form-control @error('name') is-invalid @enderror" id="input-rounded" placeholder="لطفا نام {{$info['personal'] ?? ''}} را وارد کنید">
                                @error('name')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-4">
                                <label  for="input-rounded" class="form-label ">آدرس {{$info['personal'] ?? ''}}</label>
                                <input wire:model.lazy="slug" type="text" class="form-control @error('slug') is-invalid @enderror" id="input-rounded" placeholder="لطفا آدرس {{$info['personal'] ?? ''}} را وارد کنید">
                                @error('slug')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-4">
                                <label for="formFile" class="form-label">دسته والد</label>
                                <select wire:model.lazy="parent_id" class="form-control @error('parent_id') is-invalid @enderror" data-trigger="" id="choices-single-default" name="choices-single-default">
                                    <option value="">
                                        دسته والد را انتخاب کنید
                                    </option>
                                    @foreach(\App\Models\Category::whereNull('parent_id')->where('status',1)->pluck('name','id') as $key => $role)
                                        <option value="{{$key}}">
                                            {{$role}}
                                        </option>
                                    @endforeach
                                </select>
                                @error('parent_id')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror

                            </div>

                            <div class="col-xl-4 mt-3">
                                <label class="form-label">
                                    تصویر شاخص
                                    {{$info['featured_image'] ?? ''}}
                                </label>

                                <input
                                    wire:model.lazy="featured_image"
                                    class="form-control @error('featured_image') is-invalid @enderror"
                                    type="file">

                                @error('featured_image')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- تصویر بنر --}}
                            <div class="col-xl-4 mt-3">
                                <label class="form-label">
                                    تصویر بنر
                                    {{$info['banner_image'] ?? ''}}
                                </label>

                                <input
                                    wire:model.lazy="banner_image"
                                    class="form-control @error('banner_image') is-invalid @enderror"
                                    type="file">

                                @error('banner_image')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-4 mt-3">
                                <label class="form-label">
                                    تگ‌ها
                                </label>

                                <select
                                    wire:model.lazy="tag_ids"
                                    multiple
                                    size="4"
                                    class="form-control @error('tag_ids') is-invalid @enderror @error('tag_ids.*') is-invalid @enderror">

                                    @foreach(Tag::orderBy('name')->pluck('name', 'id') as $id => $name)
                                        <option value="{{ $id }}">
                                            {{ $name }}
                                        </option>
                                    @endforeach

                                </select>

                                <small class="text-muted">
                                    برای انتخاب چند تگ، Ctrl را نگه دارید و کلیک کنید.
                                </small>

                                @error('tag_ids')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                                @error('tag_ids.*')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-12 mt-3">
                                <label  for="input-rounded" class="form-label">توضیح کوتاه {{$info['personal'] ?? ''}}</label>
                                <textarea wire:model.lazy="short_description" class="form-control @error('short_description') is-invalid @enderror"  placeholder="لطفا توضیحات کوتاه {{$info['personal'] ?? ''}} را وارد کنید"></textarea>
                                @error('short_description')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-12 mt-3">
                                <label  for="input-rounded" class="form-label">توضیحات {{$info['personal'] ?? ''}}</label>
                                <div wire:ignore>
                                    <div id="editor"></div>
                                </div>
                                @error('description')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>


                        </div>

                    </div>
                    <div class="modal-footer">
                        <div wire:loading.remove wire:target="save">
                            <button class="btn btn-primary" type="submit">
                                ذخیره تغییرات
                            </button>
                            <button class="btn btn-light" data-bs-dismiss="modal" type="button">
                                بستن
                            </button>
                        </div>

                        <!-- اسپینر لودینگ Livewire -->
                        <div wire:loading wire:target="save" class="spinner-grow text-info" role="status">
                            <span class="visually-hidden">در حال بارگیری...</span>
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
                    <h6 class="modal-title">{{$info['delete'] .' ' .$selectItem?->name}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">

                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <svg class="flex-shrink-0 me-2 svg-danger" xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="1.5rem" viewBox="0 0 24 24" width="1.5><0rem" fill="1.5rem" fill="1.5rem"00" height="24" width="24"/></g><g><g><g><path d="M15.73,3H8.27L3,8.27v7.46L8.27,21h7.46L21,15.73V8.27L15.73,3z M19,14.9L14.9,19H9.1L5,14.9V9.1L9.1,5h5.8L19,9.1V14.9z"/><rect height="6" width="2" x="11" y="7"/><rect height="2" width="2"><g="11">
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

                    <!-- اسپینر لودینگ Livewire -->
                    <div wire:loading wire:target="delete" class="spinner-grow text-info" role="status">
                        <span class="visually-hidden">در حال حذف...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
