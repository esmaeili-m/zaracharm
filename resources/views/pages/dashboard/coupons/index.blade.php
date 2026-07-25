<?php

use Livewire\Component;
use \App\Models\Coupon;
use Illuminate\Validation\Rule;

new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $discount_id;

    public $code;

    public $usage_limit;

    public $usage_per_user = 1;

    public $used_count = 0;

    public $status = true;


    public $discounts = [];
    public $search;
    public Coupon $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Coupon $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->discounts=\App\Models\Discount::active()->get();
        $this->info['header']='لیست کوپن ها';
        $this->info['create']='افزودن کوپن';
        $this->info['delete']='حذف کوپن';
        $this->info['personal']='کوپن';
        $this->info['table']['headers'] = [
            '#',
            'کد تخفیف',
            'تخفیف',
            'تعداد استفاده',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();

    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('categories.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);
        $this->loadData();
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $this->info['personal']." موفقیت آپدیت شد."
        );
    }
    public function delete()
    {
        abort_if(!auth()->user()->can('categories.delete'), 403);

        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->loadData();
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: $this->info['personal']." موفقیت حذف شد."
            );

            $this->resetData('close');
        }

    }
    public function loadData()
    {
        $this->data = $this->model->get();
    }
    public function get_data($id)
    {
        $this->selectItem = $this->model->findOrFail($id);

        $this->discount_id = $this->selectItem->discount_id;
        $this->code = $this->selectItem->code;
        $this->usage_limit = $this->selectItem->usage_limit;
        $this->usage_per_user = $this->selectItem->usage_per_user;
        $this->used_count = $this->selectItem->used_count;
        $this->status = $this->selectItem->status;
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data','discounts');
        }else{
            $this->resetExcept(['selectItem','model','info','data','discounts']);
            $this->dispatch('close-modal');
        }
        $this->dispatch('editor-update');

    }
    public function saveEditorImage()
    {
        if (!$this->editorImage) {
            abort(400, 'No image uploaded');
        }

        $path = $this->editorImage->store('editor-images', 'public');

        return url('/media/' . $path);
    }

    public function updatedTitle()
    {
        $this->slug = preg_replace('/\s+/', '-', trim($this->title));
    }
    public function rules()
    {
        return [

            'discount_id' => [
                'required',
                'exists:discounts,id',
            ],


            'code' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'unique:coupons,code,' . ($this->selectItem?->id ?? 'NULL'),
            ],


            'usage_limit' => [
                'nullable',
                'integer',
                'min:1',
            ],


            'usage_per_user' => [
                'required',
                'integer',
                'min:1',
            ],


            'used_count' => [
                'required',
                'integer',
                'min:0',
            ],


            'status' => [
                'required',
                'boolean',
            ],

        ];
    }
    public function messages()
    {
        return [

            // discount
            'discount_id.required'
            => 'انتخاب تخفیف الزامی است.',

            'discount_id.exists'
            => 'تخفیف انتخاب شده معتبر نیست.',



            // code
            'code.required'
            => 'وارد کردن کد تخفیف الزامی است.',

            'code.string'
            => 'کد تخفیف باید متن باشد.',

            'code.min'
            => 'کد تخفیف باید حداقل ۳ کاراکتر باشد.',

            'code.max'
            => 'کد تخفیف نباید بیشتر از ۵۰ کاراکتر باشد.',

            'code.unique'
            => 'این کد تخفیف قبلاً ثبت شده است.',



            // usage limit
            'usage_limit.integer'
            => 'تعداد کل استفاده باید عدد باشد.',

            'usage_limit.min'
            => 'تعداد کل استفاده حداقل باید ۱ باشد.',



            // usage per user
            'usage_per_user.required'
            => 'تعداد استفاده هر کاربر الزامی است.',

            'usage_per_user.integer'
            => 'تعداد استفاده هر کاربر باید عدد باشد.',

            'usage_per_user.min'
            => 'تعداد استفاده هر کاربر حداقل باید ۱ باشد.',



            // used count
            'used_count.required'
            => 'تعداد استفاده شده الزامی است.',

            'used_count.integer'
            => 'تعداد استفاده شده باید عدد باشد.',

            'used_count.min'
            => 'تعداد استفاده شده نمی‌تواند منفی باشد.',



            // status
            'status.required'
            => 'وضعیت کوپن الزامی است.',

            'status.boolean'
            => 'وضعیت انتخاب شده معتبر نیست.',

        ];
    }

    public function save(){
        abort_if(!auth()->user()->can('categories.create'), 403);
        $data= $this->validate();

        $item = $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);
        // رفرش دیتا
        $this->loadData();

        // ریست فرم
        $this->resetData('close');
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $this->selectItem
                ? $this->info['personal'] . ' با موفقیت ویرایش شد.'
                : $this->info['personal'] . ' جدید با موفقیت ایجاد شد.',
        );
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
            @can('categories.view')

                <a href="{{route('brands.trash')}}" class="btn btn-warning-light btn-wave me-2">
                    <i class="bx bx-trash align-middle">
                    </i>
                    سطل آشغال
                </a>
            @endcan
            @can('categories.create')
                <button wire:click="resetData()" data-bs-effect="effect-flip-horizontal" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave me-0">
                    <i class="ri-add-line align-middle">
                    </i>
                    {{$info['create']}}
                </button>
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
                                    <th scope="col">
                                        {{$h}}
                                    </th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody  id="simple-list">
                            @php($counter=1)
                            @forelse($data ?? [] as $item)

                                <tr data-id="{{ $item->id }}" wire:key="{{ $item->id }}">

                                    <th scope="row">
                                        {{$counter}}
                                    </th>


                                    {{-- کد تخفیف --}}
                                    <td>
                                        <strong>
                                            {{$item->code}}
                                        </strong>
                                    </td>


                                    {{-- تخفیف مربوطه --}}
                                    <td>
                                        {{$item->discount->title ?? '-'}}
                                    </td>


                                    {{-- تعداد استفاده --}}
                                    <td>
                                        {{$item->used_count}}
                                        @if($item->usage_limit)
                                            /
                                            {{$item->usage_limit}}
                                        @else
                                            /
                                            نامحدود
                                        @endif
                                    </td>


                                    {{-- وضعیت --}}
                                    <td>

                                        @can('categories.edit')

                                            <span
                                                style="cursor:pointer"
                                                wire:click="change_status({{$item->id}})"
                                                wire:loading.attr="disabled"
                                                class="badge bg-outline-{{$item->status == 1 ? 'success' : 'danger'}}">


                <span wire:target="change_status" wire:loading.remove>
                    {{$item->status == 1 ? 'فعال' : 'غیرفعال'}}
                </span>


                <span wire:target="change_status" wire:loading>
                    در حال تغییر...
                </span>


            </span>

                                        @endcan

                                    </td>



                                    {{-- عملیات --}}
                                    <td>

                                        <div class="hstack gap-2 flex-wrap">


                                            @can('categories.edit')

                                                <a
                                                    data-bs-toggle="modal"
                                                    href="#create"
                                                    wire:click="get_data({{$item->id}})"
                                                    class="text-info fs-14 lh-1">

                                                    <i class="ri-edit-line"></i>

                                                </a>

                                            @endcan



                                            @can('categories.delete')

                                                <a
                                                    data-bs-toggle="modal"
                                                    href="#delete"
                                                    wire:click="get_data({{$item->id}})"
                                                    class="text-danger fs-14 lh-1">

                                                    <i class="ri-delete-bin-5-line"></i>

                                                </a>

                                            @endcan


                                        </div>

                                    </td>


                                </tr>


                                @php($counter++)


                            @empty


                                <tr>

                                    <td colspan="{{ count($info['table']['headers'] ?? []) }}"
                                        class="text-center py-5 text-muted">

                                        <i class="ri-coupon-3-line fs-1 d-block mb-2"></i>

                                        <strong>
                                            کدی برای نمایش وجود ندارد.
                                        </strong>

                                        <div class="small mt-1">
                                            پس از ایجاد اولین کد تخفیف، اطلاعات نمایش داده خواهد شد.
                                        </div>

                                    </td>

                                </tr>


                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered text-center modal-lg modal-dialog-scrollable">
            <div class="modal-content modal-content-demo">

                <div class="modal-header">
                    <h6 class="modal-title">
                        {{$info['create']}}
                    </h6>

                    <button aria-label="Close"
                            class="btn-close"
                            data-bs-dismiss="modal">
                    </button>
                </div>


                <div class="modal-body text-start">

                    <form wire:submit.prevent="save()" id="save">

                        <div class="row">


                            {{-- تخفیف --}}
                            <div class="col-xl-6">

                                <label class="form-label">
                                    تخفیف
                                </label>

                                <select
                                    wire:model.lazy="discount_id"
                                    class="form-select @error('discount_id') is-invalid @enderror">

                                    <option value="">
                                        انتخاب تخفیف
                                    </option>


                                    @foreach($discounts ?? [] as $discount)

                                        <option value="{{ $discount->id }}">
                                            {{ $discount->title }}
                                        </option>

                                    @endforeach


                                </select>


                                @error('discount_id')
                                <div class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror

                            </div>



                            {{-- کد تخفیف --}}
                            <div class="col-xl-6">

                                <label class="form-label">
                                    کد تخفیف
                                </label>


                                <input
                                    wire:model.lazy="code"
                                    type="text"
                                    class="form-control @error('code') is-invalid @enderror"
                                    placeholder="مثلا SUMMER20">


                                @error('code')
                                <div class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror

                            </div>




                            {{-- محدودیت استفاده --}}
                            <div class="col-xl-4 mt-3">

                                <label class="form-label">
                                    تعداد کل استفاده
                                </label>


                                <input
                                    wire:model.lazy="usage_limit"
                                    type="number"
                                    min="1"
                                    class="form-control @error('usage_limit') is-invalid @enderror"
                                    placeholder="خالی = نامحدود">


                                @error('usage_limit')
                                <div class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror


                            </div>




                            {{-- استفاده هر کاربر --}}
                            <div class="col-xl-4 mt-3">

                                <label class="form-label">
                                    تعداد استفاده هر کاربر
                                </label>


                                <input
                                    wire:model.lazy="usage_per_user"
                                    type="number"
                                    min="1"
                                    class="form-control @error('usage_per_user') is-invalid @enderror">


                                @error('usage_per_user')
                                <div class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror


                            </div>




                            {{-- تعداد استفاده شده --}}
                            <div class="col-xl-4 mt-3">

                                <label class="form-label">
                                    تعداد استفاده شده
                                </label>


                                <input
                                    wire:model.lazy="used_count"
                                    type="number"
                                    min="0"
                                    class="form-control @error('used_count') is-invalid @enderror">


                                @error('used_count')
                                <div class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror


                            </div>




                            {{-- وضعیت --}}
                            <div class="col-xl-4 mt-3">

                                <label class="form-label">
                                    وضعیت
                                </label>


                                <select
                                    wire:model.lazy="status"
                                    class="form-select @error('status') is-invalid @enderror">

                                    <option value="1">
                                        فعال
                                    </option>

                                    <option value="0">
                                        غیرفعال
                                    </option>

                                </select>


                                @error('status')
                                <div class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror

                            </div>


                        </div>


                    </form>


                </div>


                <div class="modal-footer">

                    <div wire:loading.remove wire:target="save">

                        <button
                            class="btn btn-info"
                            form="save"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            type="submit">

                            ذخیره تغییرات

                        </button>


                        <button
                            class="btn btn-light"
                            data-bs-dismiss="modal"
                            type="button">

                            بستن

                        </button>


                    </div>



                    <div wire:loading wire:target="save"
                         class="spinner-grow text-info">

                    <span class="visually-hidden">
                        در حال بارگیری...
                    </span>

                    </div>


                </div>


            </div>
        </div>
    </div>
    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered text-center " role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{$info['delete'] .' ' .$selectItem?->title}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <button class="btn btn-info" wire:click="delete()">
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
    @push('styles')
        <link href="{{asset('dashboard')}}/libs/quill/quill.snow.css" rel="stylesheet"/>
        <link href="{{asset('dashboard')}}/libs/quill/quill.bubble.css" rel="stylesheet"/>
    @endpush
    @push('scripts')
        <script>
            var toolbarOptions = [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                [{ 'font': [] }],
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote', 'code-block'],

                [{ 'header': 1 }, { 'header': 2 }],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'indent': '-1' }, { 'indent': '+1' }],
                [{ 'direction': 'rtl' }],

                [{ 'size': ['small', false, 'large', 'huge'] }],

                [{ 'color': [] }, { 'background': [] }],
                [{ 'align': [] }],

                ['image', 'video'],
            ];
            var quill = new Quill('#editor', {
                modules: {
                    toolbar: toolbarOptions
                },
                theme: 'snow'
            });
            quill.on('text-change', function() {
            @this.set('description',quill.root.innerHTML,false);
            });
            Livewire.on('editor-update', () => {
                quill.root.innerHTML = @this.get('description');
            });

        </script>
        <script src="{{asset('dashboard')}}/libs/sortablejs/Sortable.min.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const simple = document.getElementById('simple-list');
                new Sortable(simple, {
                    animation: 150,
                    onEnd: function () {
                        const ids = Array.from(simple.children)
                            .map(item => item.dataset.id);

                        Livewire.dispatch('updateOrder', {
                            ids: ids
                        });
                    }
                });

            });
        </script>
    @endpush
</div>
