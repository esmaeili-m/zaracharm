<?php

use Livewire\Component;
use \App\Models\Discount;
use Illuminate\Validation\Rule;
use App\Enums\DiscountType;
use Illuminate\Validation\Rules\Enum;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $title;
    public $type;
    public $value;
    public $maximum_discount;
    public $minimum_purchase;
    public $starts_at;
    public $ends_at;
    public $status = true;
    public $search;
    public Discount $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Discount $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->info['header']='لیست تخفیف ها';
        $this->info['create']='افزودن تخفیف';
        $this->info['delete']='حذف تخفیف';
        $this->info['personal']='تخفیف';
        $this->info['table']['headers']=[
            '#',
            'نام',
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
        $query = $this->model->where(function ($query) {
            $query->where('title', 'LIKE', '%' . $this->search . '%');
        });

        $this->data = $query->get();
    }
    public function get_data($id)
    {
        $this->selectItem = $this->model->findOrFail($id);

        $this->title = $this->selectItem->title;
        $this->type = $this->selectItem->type;
        $this->value = $this->selectItem->value;
        $this->maximum_discount = $this->selectItem->maximum_discount;
        $this->minimum_purchase = $this->selectItem->minimum_purchase;
        $this->starts_at = $this->selectItem->starts_at ?? null;
        $this->ends_at = $this->selectItem->ends_at ?? null;
        $this->status = $this->selectItem->status;
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
        }else{
            $this->resetExcept(['selectItem','model','info','data']);
            $this->dispatch('close-modal');
        }

    }

    public function rules()
    {
        $discountId = $this->selectItem?->id;

        return [

            'title' => [
                'required',
                'string',
                'min:2',
                'max:200',
            ],

            'type' => [
                'required',
                new Enum(DiscountType::class),
            ],

            'value' => [
                'required',
                'integer',
                'min:1',
            ],

            'maximum_discount' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'minimum_purchase' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'starts_at' => [
                'nullable',
                'date',
            ],

            'ends_at' => [
                'nullable',
                'date',
                'after_or_equal:starts_at',
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

            // title
            'title.required' => 'وارد کردن عنوان تخفیف الزامی است.',
            'title.string'   => 'عنوان تخفیف باید متن باشد.',
            'title.min'      => 'عنوان تخفیف باید حداقل ۲ کاراکتر باشد.',
            'title.max'      => 'عنوان تخفیف نباید بیشتر از ۲۰۰ کاراکتر باشد.',

            // type
            'type.required' => 'نوع تخفیف را انتخاب کنید.',
            'type.enum'     => 'نوع تخفیف انتخاب شده معتبر نیست.',

            // value
            'value.required' => 'مقدار تخفیف را وارد کنید.',
            'value.integer'  => 'مقدار تخفیف باید عدد صحیح باشد.',
            'value.min'      => 'مقدار تخفیف باید بزرگ‌تر از صفر باشد.',

            // maximum_discount
            'maximum_discount.integer' => 'حداکثر مبلغ تخفیف باید عدد صحیح باشد.',
            'maximum_discount.min'     => 'حداکثر مبلغ تخفیف نمی‌تواند منفی باشد.',

            // minimum_purchase
            'minimum_purchase.integer' => 'حداقل مبلغ خرید باید عدد صحیح باشد.',
            'minimum_purchase.min'     => 'حداقل مبلغ خرید نمی‌تواند منفی باشد.',

            // starts_at
            'starts_at.date' => 'تاریخ شروع معتبر نیست.',

            // ends_at
            'ends_at.date' => 'تاریخ پایان معتبر نیست.',
            'ends_at.after_or_equal' => 'تاریخ پایان باید بعد از تاریخ شروع یا برابر با آن باشد.',

            // status
            'status.required' => 'وضعیت را انتخاب کنید.',
            'status.boolean'  => 'وضعیت انتخاب شده معتبر نیست.',
        ];
    }

    public function save(){
        abort_if(!auth()->user()->can('categories.create'), 403);
        $data= $this->validate();
        $item = $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);

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

    #[\Livewire\Attributes\On('updateOrder')]
    public function updateOrder($ids)
    {
        abort_if(!auth()->user()->can('categories.edit'), 403);

        foreach ($ids as $index => $id) {
            $this->model->where('id', $id)->update([
                'sort' => $index + 1
            ]);
        }
        $this->loadData();
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
                                <tr data-id="{{ $item->id }}" wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{$counter}}
                                    </th>
                                    <td>
                                        {{$item->title}}
                                    </td>
                                    <td>
                                        @can('categories.edit')

                                            <span style="cursor: pointer" wire:click="change_status({{$item->id}})"
                                                  wire:loading.attr="disabled"
                                                  class="badge bg-outline-{{$item->status == 1 ? 'success' : 'danger'}}">
                                            <span wire:target="change_status" wire:loading.remove>{{$item->status == 1 ? 'فعال' : 'غیرفعال'}}</span>
                                            <span wire:target="change_status" wire:loading>در حال تغییر...</span>
                                     </span>
                                        @endcan
                                    </td>
                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            @can('categories.create')

                                                <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                        class="ri-edit-line"></i></a>
                                            @endcan
                                            @can('categories.delete')

                                                <a  href="{{route('discounts.target',$item->id)}}"
                                                    class="text-primary fs-14 lh-1"><i class="ri-price-tag-3-line"></i></a>
                                            @endcan
                                                @can('categories.delete')

                                                <a  data-bs-toggle="modal" href="#delete" wire:click="get_data({{$item->id}})"  class="text-danger fs-14 lh-1"><i
                                                        class="ri-delete-bin-5-line"></i></a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @php($counter++)
                            @empty
                                <tr>
                                    <td colspan="{{ count($info['table']['headers'] ?? []) }}" class="text-center py-5 text-muted">
                                        <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                        <strong>موردی برای نمایش وجود ندارد.</strong>
                                        <div class="small mt-1">
                                            پس از ایجاد اولین آیتم، اطلاعات در این بخش نمایش داده خواهد شد.
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
        <div class="modal-dialog modal-dialog-centered text-center modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{$info['create']}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="save()"  id="save">
                        <div class="row">

                            {{-- عنوان --}}
                            <div class="col-xl-6">
                                <label class="form-label">عنوان تخفیف</label>

                                <input
                                    wire:model.lazy="title"
                                    type="text"
                                    class="form-control @error('title') is-invalid @enderror"
                                    placeholder="عنوان کمپین تخفیف را وارد کنید">

                                @error('title')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- نوع تخفیف --}}
                            <div class="col-xl-6">
                                <label class="form-label">نوع تخفیف</label>

                                <select
                                    wire:model.lazy="type"
                                    class="form-select @error('type') is-invalid @enderror">

                                    <option value="">انتخاب کنید</option>

                                    @foreach(DiscountType::options() as $value => $title)
                                        <option value="{{ $value }}">
                                            {{ $title }}
                                        </option>
                                    @endforeach

                                </select>

                                @error('type')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- مقدار تخفیف --}}
                            <div class="col-xl-4 mt-3">
                                <label class="form-label">مقدار تخفیف</label>

                                <input
                                    wire:model.lazy="value"
                                    type="number"
                                    min="0"
                                    class="form-control @error('value') is-invalid @enderror"
                                    placeholder="مثلاً 100000 یا 20">

                                @error('value')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- حداکثر تخفیف --}}
                            <div class="col-xl-4 mt-3">
                                <label class="form-label">حداکثر مبلغ تخفیف</label>

                                <input
                                    wire:model.lazy="maximum_discount"
                                    type="number"
                                    min="0"
                                    class="form-control @error('maximum_discount') is-invalid @enderror"
                                    placeholder="اختیاری">

                                @error('maximum_discount')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- حداقل خرید --}}
                            <div class="col-xl-4 mt-3">
                                <label class="form-label">حداقل مبلغ خرید</label>

                                <input
                                    wire:model.lazy="minimum_purchase"
                                    type="number"
                                    min="0"
                                    class="form-control @error('minimum_purchase') is-invalid @enderror"
                                    placeholder="اختیاری">

                                @error('minimum_purchase')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- شروع --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">تاریخ شروع</label>

                                <input
                                    wire:model.lazy="starts_at"
                                    type="text"
                                    data-jdp

                                    class="form-control @error('starts_at') is-invalid @enderror">

                                @error('starts_at')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- پایان --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">تاریخ پایان</label>

                                <input
                                    wire:model.lazy="ends_at"
                                    type="text"
                                    data-jdp
                                    class="form-control @error('ends_at') is-invalid @enderror">

                                @error('ends_at')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- وضعیت --}}
                            <div class="col-xl-4 mt-3">
                                <label class="form-label">وضعیت</label>

                                <select
                                    wire:model.lazy="status"
                                    class="form-select @error('status') is-invalid @enderror">

                                    <option value="1">فعال</option>
                                    <option value="0">غیرفعال</option>

                                </select>

                                @error('status')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                        </div>
                    </form>

                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="save">
                        <button class="btn btn-info"
                                form="save"
                                wire:loading.attr="disabled"
                                wire:target="featured_image,banner_image,save"
                                type="submit">
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
        <link rel="stylesheet" href="{{asset('dashboard/libs/datepicker/jalalidatepicker.min.css')}}">
        <script src="{{asset('dashboard/libs/datepicker/jalalidatepicker.min.js')}}"></script>
        <script>
            jalaliDatepicker.startWatch();
        </script>
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
