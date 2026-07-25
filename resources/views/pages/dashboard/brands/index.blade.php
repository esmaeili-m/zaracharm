<?php

use Livewire\Component;
use \App\Models\Brand;
use Illuminate\Validation\Rule;

new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $title='';
    public $slug;
    public $description;
    public $website;
    public $country;
    public $featured_image;
    public $banner_image;
    public $editorImage;
    public $search;
    public Brand $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Brand $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->info['header']='لیست برند ها';
        $this->info['create']='افزودن برند';
        $this->info['delete']='حذف برند';
        $this->info['personal']='برند';
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
                $query->where('title', 'LIKE', '%' . $this->search . '%')
                    ->orWhere('description', 'LIKE', '%' . $this->search . '%');
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
        $categoryId = $this->selectItem?->id;

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
                Rule::unique('brands', 'slug')->ignore($categoryId),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'website' => [
                'nullable',
                'string',
            ],

            'country' => [
                'nullable',
                'string',
            ],

            'featured_image' => [
                $this->selectItem ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'banner_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],
        ];
    }

    public function messages()
    {
        return [

            // title
            'title.required' => 'وارد کردن نام برند الزامی است.',
            'title.string'   => 'نام برند باید متن باشد.',
            'title.min'      => 'نام برند باید حداقل ۲ کاراکتر باشد.',
            'title.max'      => 'نام برند نباید بیشتر از ۲۰۰ کاراکتر باشد.',

            // slug
            'slug.required' => 'وارد کردن اسلاگ الزامی است.',
            'slug.string'   => 'اسلاگ باید متن باشد.',
            'slug.min'      => 'اسلاگ باید حداقل ۳ کاراکتر باشد.',
            'slug.max'      => 'اسلاگ نباید بیشتر از ۲۰۰ کاراکتر باشد.',
            'slug.regex'    => 'اسلاگ فقط می‌تواند شامل حروف فارسی، انگلیسی، اعداد، خط تیره (-) و زیرخط (_) باشد.',
            'slug.unique'   => 'این اسلاگ قبلاً ثبت شده است.',

            // description
            'description.string' => 'توضیحات باید متن باشد.',

            // website
            'website.string' => 'آدرس وب‌سایت باید متن باشد.',

            // country
            'country.string' => 'نام کشور باید متن باشد.',

            // featured image
            'featured_image.required' => 'وارد کردن لوگو برند الزامی است.',
            'featured_image.image' => 'فایل انتخاب شده برای لوگو معتبر نیست.',
            'featured_image.mimes' => 'لوگو باید با فرمت jpg، jpeg، png یا webp باشد.',
            'featured_image.max'   => 'حجم لوگو نباید بیشتر از ۲ مگابایت باشد.',

            // banner image
            'banner_image.image' => 'فایل انتخاب شده برای بنر معتبر نیست.',
            'banner_image.mimes' => 'بنر باید با فرمت jpg، jpeg، png یا webp باشد.',
            'banner_image.max'   => 'حجم بنر نباید بیشتر از ۴ مگابایت باشد.',
        ];
    }

    public function save(){
        abort_if(!auth()->user()->can('categories.create'), 403);
        $data= $this->validate();
        unset($data['featured_image']);
        unset($data['banner_image']);
        $item = $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);
        if ($this->featured_image) {

            $item->media()
                ->where('collection', 'featured_image')
                ->delete();

            $this->upload(
                $this->featured_image,
                $item,
                'featured_image'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | تصویر بنر
        |--------------------------------------------------------------------------
        */

        if ($this->banner_image) {

            $item->media()
                ->where('collection', 'banner_image')
                ->delete();

            $this->upload(
                $this->banner_image,
                $item,
                'banner_image'
            );
        }
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
                            <div class="col-xl-4">
                                <label  for="input-rounded" class="form-label ">عنوان {{$info['personal'] ?? ''}}</label>
                                <input wire:model.lazy="title" type="text" class="form-control @error('title') is-invalid @enderror" id="input-rounded" placeholder="لطفا نام {{$info['personal'] ?? ''}} را وارد کنید">
                                @error('title')
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
                                <label  for="input-rounded" class="form-label "> آدرس سایت {{$info['personal'] ?? ''}}</label>
                                <input wire:model.lazy="website" type="text" class="form-control @error('website') is-invalid @enderror" id="input-rounded" placeholder="لطفا آدرس {{$info['personal'] ?? ''}} را وارد کنید">
                                @error('website')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror

                            </div>

                            <div class="col-xl-4  mt-3">
                                <label  for="input-rounded" class="form-label ">کشور {{$info['personal'] ?? ''}}</label>
                                <input wire:model.lazy="country" type="text" class="form-control @error('country') is-invalid @enderror" id="input-rounded" placeholder="لطفا آدرس {{$info['personal'] ?? ''}} را وارد کنید">
                                @error('country')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-4 mt-3">
                                <label class="form-label">
                                    لوگو
                                    {{$info['featured_image'] ?? ''}}
                                </label>
                                <div x-data="{ progress: 0 }"
                                     x-on:livewire-upload-start="progress = 0"
                                     x-on:livewire-upload-finish="progress = 100"
                                     x-on:livewire-upload-error="progress = 0"
                                     x-on:livewire-upload-progress="progress = $event.detail.progress">

                                    <input
                                        wire:model.lazy="featured_image"
                                        class="form-control mb-1 @error('featured_image') is-invalid @enderror"
                                        type="file">

                                    <div class="progress mt-2" x-show="progress > 0">
                                        <div class="progress-bar"
                                             role="progressbar"
                                             :style="'width: ' + progress + '%'">
                                            <span x-text="progress + '%'"></span>
                                        </div>
                                    </div>
                                </div>
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
                                <div x-data="{ progress: 0 }"
                                     x-on:livewire-upload-start="progress = 0"
                                     x-on:livewire-upload-finish="progress = 100"
                                     x-on:livewire-upload-error="progress = 0"
                                     x-on:livewire-upload-progress="progress = $event.detail.progress">

                                    <input
                                        wire:model.lazy="banner_image"
                                        class="form-control mb-1 @error('banner_image') is-invalid @enderror"
                                        type="file">

                                    <div class="progress mt-2" x-show="progress > 0">
                                        <div class="progress-bar"
                                             role="progressbar"
                                             :style="'width: ' + progress + '%'">
                                            <span x-text="progress + '%'"></span>
                                        </div>
                                    </div>
                                </div>
                                @error('banner_image')
                                <div class="invalid-feedback d-block">
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
