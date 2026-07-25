<?php

use Livewire\Component;
use \App\Models\Inventory;
use Illuminate\Validation\Rule;

new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $title;
    public $slug;
    public $address;
    public $phone;
    public $sort = 0;
    public $status = true;
    public $search;
    public Inventory $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Inventory $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->info['header']='لیست انبار ها';
        $this->info['create']='افزودن انبار';
        $this->info['delete']='حذف انبار';
        $this->info['personal']='انبار';
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

        $this->data = $query->orderBy('sort')->get();
    }
    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->title=$this->selectItem->title;
        $this->slug=$this->selectItem->slug;
        $this->description=$this->selectItem->description;
        $this->website=$this->selectItem->website;
        $this->country=$this->selectItem->country;
        $this->dispatch('editor-update');
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
        }else{
            $this->resetExcept(['selectItem','model','info','data']);
            $this->dispatch('close-modal');
        }
        $this->dispatch('editor-update');

    }


    public function updatedTitle()
    {
        $this->slug = preg_replace('/\s+/', '-', trim($this->title));
    }

    public function rules()
    {
        $inventoryId = $this->selectItem?->id;

        return [

            'title' => [
                'required',
                'string',
                'min:2',
                'max:200',
            ],

            'slug' => [
                'required',
                'string',
                'min:3',
                'max:200',
                'regex:/^[a-zA-Z0-9\-_\p{Arabic}]+$/u',
                Rule::unique('inventories', 'slug')->ignore($inventoryId),
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'sort' => [
                'required',
                'integer',
                'min:0',
            ],

        ];
    }

    public function messages()
    {
        return [

            // title
            'title.required' => 'وارد کردن عنوان انبار الزامی است.',
            'title.string'   => 'عنوان انبار باید متن باشد.',
            'title.min'      => 'عنوان انبار باید حداقل ۲ کاراکتر باشد.',
            'title.max'      => 'عنوان انبار نباید بیشتر از ۲۰۰ کاراکتر باشد.',

            // slug
            'slug.required' => 'وارد کردن اسلاگ الزامی است.',
            'slug.string'   => 'اسلاگ باید متن باشد.',
            'slug.min'      => 'اسلاگ باید حداقل ۳ کاراکتر باشد.',
            'slug.max'      => 'اسلاگ نباید بیشتر از ۲۰۰ کاراکتر باشد.',
            'slug.regex'    => 'اسلاگ فقط می‌تواند شامل حروف فارسی، انگلیسی، اعداد، خط تیره (-) و زیرخط (_) باشد.',
            'slug.unique'   => 'این اسلاگ قبلاً ثبت شده است.',

            // address
            'address.string' => 'آدرس باید متن باشد.',
            'address.max'    => 'آدرس نباید بیشتر از ۱۰۰۰ کاراکتر باشد.',

            // phone
            'phone.string' => 'شماره تماس باید متن باشد.',
            'phone.max'    => 'شماره تماس نباید بیشتر از ۲۰ کاراکتر باشد.',

            // sort
            'sort.required' => 'وارد کردن ترتیب نمایش الزامی است.',
            'sort.integer'  => 'ترتیب نمایش باید یک عدد صحیح باشد.',
            'sort.min'      => 'ترتیب نمایش نمی‌تواند کمتر از صفر باشد.',


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

                <a href="{{route('inventories.trash')}}" class="btn btn-warning-light btn-wave me-2">
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
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content modal-content-demo">

                <div class="modal-header">
                    <h6 class="modal-title">{{ $info['create'] }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body text-start">

                    <form wire:submit.prevent="save" id="save">

                        <div class="row">

                            {{-- عنوان --}}
                            <div class="col-md-6">
                                <label class="form-label">
                                    عنوان {{ $info['personal'] ?? '' }}
                                </label>

                                <input
                                    wire:model.lazy="title"
                                    type="text"
                                    class="form-control @error('title') is-invalid @enderror"
                                    placeholder="عنوان انبار را وارد کنید">

                                @error('title')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- اسلاگ --}}
                            <div class="col-md-6">
                                <label class="form-label">
                                    اسلاگ
                                </label>

                                <input
                                    wire:model.lazy="slug"
                                    type="text"
                                    class="form-control @error('slug') is-invalid @enderror"
                                    placeholder="warehouse-tehran">

                                @error('slug')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- شماره تماس --}}
                            <div class="col-md-6 mt-3">
                                <label class="form-label">
                                    شماره تماس
                                </label>

                                <input
                                    wire:model.lazy="phone"
                                    type="text"
                                    class="form-control @error('phone') is-invalid @enderror"
                                    placeholder="09121234567">

                                @error('phone')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- ترتیب نمایش --}}
                            <div class="col-md-6 mt-3">
                                <label class="form-label">
                                    ترتیب نمایش
                                </label>

                                <input
                                    wire:model.lazy="sort"
                                    type="number"
                                    min="0"
                                    class="form-control @error('sort') is-invalid @enderror">

                                @error('sort')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- آدرس --}}
                            <div class="col-12 mt-3">
                                <label class="form-label">
                                    آدرس
                                </label>

                                <textarea
                                    wire:model.lazy="address"
                                    rows="4"
                                    class="form-control @error('address') is-invalid @enderror"
                                    placeholder="آدرس کامل انبار را وارد کنید"></textarea>

                                @error('address')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- وضعیت --}}


                        </div>

                    </form>

                </div>

                <div class="modal-footer">

                    <div wire:loading.remove wire:target="save">

                        <button
                            form="save"
                            type="submit"
                            class="btn btn-info"
                            wire:loading.attr="disabled">

                            ذخیره تغییرات

                        </button>

                        <button
                            type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">

                            بستن

                        </button>

                    </div>

                    <div wire:loading wire:target="save"
                         class="spinner-grow text-info"
                         role="status">

                    <span class="visually-hidden">
                        در حال بارگذاری...
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
    @push('scripts')

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
