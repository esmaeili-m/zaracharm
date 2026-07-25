<?php

use Livewire\Component;
use \App\Models\Service;
use \App\Models\Tag;
use \App\Services\FileUploadService;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;

    public $title = '';

    public $slug = '';

    public $description = '';

    public $short_description = '';


    public $tag_ids = [];


    public $featured_image;

    public $banner_image;

    public $editorImage;

    public $search;
    public Service $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Service $model)
    {
        abort_if(!auth()->user()->can('services.view'), 403);

        $this->model=$model;
        $this->info['header']='لیست خدمات';
        $this->info['create']='افزودن خدمت';
        $this->info['delete']='حذف خدمت';
        $this->info['personal']='خدمت';
        $this->info['table']['headers']=[
            '#',
            'نام خدمت',
            'تگ‌ها',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('services.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);
        $this->loadData();
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'خدمت با موفقیت ویرایش شد.'
        );
    }
    public function delete()
    {
        abort_if(!auth()->user()->can('services.delete'), 403);

        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->loadData();
            $this->resetData('close');
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: 'خدمت با موفقیت حذف شد.'
            );
        }

    }
    public function loadData()
    {
        $query = $this->model
            ->with('tags')
            ->where(function ($query) {

                $query->where('title', 'LIKE', '%' . $this->search . '%')
                    ->orWhere('description', 'LIKE', '%' . $this->search . '%')
                    ->orWhere('short_description', 'LIKE', '%' . $this->search . '%')
                    ->orWhereHas('tags', function ($q) {
                        $q->where('name', 'LIKE', '%' . $this->search . '%');
                    });
            });

        $this->data = $query->get();
    }


    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->title=$this->selectItem->title;
        $this->slug=$this->selectItem->slug;
        $this->description=$this->selectItem->description;
        $this->short_description=$this->selectItem->short_description;
        $this->tag_ids = $this->selectItem->tags()->pluck('tags.id')->toArray();
        $this->featured_image=$this->selectItem->featured_image;
        $this->banner_image=$this->selectItem->banner_image;
        $this->dispatch('editor-update');
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
            $this->dispatch('editor-update');

        }else{
            $this->resetExcept(['selectItem','model','info','data']);
            $this->dispatch('close-modal');

        }
    }
    public function saveEditorImage()
    {
        if (!$this->editorImage) {
            abort(400, 'No image uploaded');
        }

        $path = $this->editorImage->store('editor-images', 'public');

        return url('/media/' . $path);
    }

    public function rules()
    {
        $itemId = $this->selectItem?->id ?? null;

        return [
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                'unique:services,slug,'.$itemId,
            ],

            'short_description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'description' => [
                'required',
                'string',
            ],
            'tag_ids' => [
                'nullable',
                'array',
            ],
            'tag_ids.*' => [
                'integer',
                'exists:tags,id',
            ],

            'featured_image' => [
                'nullable',
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

    public function messages(): array
    {
        return [

            'title.required' => 'عنوان خدمت الزامی است.',
            'title.string' => 'عنوان خدمت معتبر نیست.',
            'title.max' => 'عنوان خدمت نباید بیشتر از ۲۵۵ کاراکتر باشد.',

            'slug.required' => 'اسلاگ خدمت الزامی است.',
            'slug.string' => 'اسلاگ معتبر نیست.',
            'slug.max' => 'اسلاگ نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'slug.alpha_dash' => 'اسلاگ فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.',
            'slug.unique' => 'این اسلاگ قبلاً ثبت شده است.',

            'short_description.string' => 'توضیحات کوتاه معتبر نیست.',
            'short_description.max' => 'توضیحات کوتاه نباید بیشتر از ۱۰۰۰ کاراکتر باشد.',

            'description.required' => 'متن خدمت الزامی است.',
            'description.string' => 'متن خدمت معتبر نیست.',

            'tag_ids.array' => 'تگ‌ها باید به صورت لیست باشند.',
            'tag_ids.*.integer' => 'شناسه تگ معتبر نیست.',
            'tag_ids.*.exists' => 'یکی از تگ‌های انتخاب شده وجود ندارد.',


            'featured_image.image' => 'فایل تصویر شاخص معتبر نیست.',
            'featured_image.mimes' => 'تصویر شاخص باید با فرمت jpg, jpeg, png یا webp باشد.',
            'featured_image.max' => 'حجم تصویر شاخص نباید بیشتر از ۲ مگابایت باشد.',

            'banner_image.image' => 'فایل بنر معتبر نیست.',
            'banner_image.mimes' => 'بنر باید با فرمت jpg, jpeg, png یا webp باشد.',
            'banner_image.max' => 'حجم بنر نباید بیشتر از ۴ مگابایت باشد.',
        ];
    }
    public function save()
    {
        abort_if(!auth()->user()->can('services.create'), 403);

        $data = $this->validate();

        $tagIds = $data['tag_ids'] ?? [];
        unset($data['featured_image']);
        unset($data['banner_image']);
        unset($data['tag_ids']);
        $item = $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);

        $item->tags()->sync($tagIds);

        /*
        |--------------------------------------------------------------------------
        | تصویر شاخص
        |--------------------------------------------------------------------------
        */

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

        // پیام موفقیت
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $this->selectItem
                ? 'خدمت با موفقیت ویرایش شد.'
                : 'خدمت جدید با موفقیت ایجاد شد.'
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
            @can('services.create')

            <a href="{{route('services.trash')}}" class="btn btn-warning-light btn-wave me-2">
                <i class="bx bx-trash align-middle">
                </i>
                سطل آشغال
            </a>
            @endcan
                @can('services.view')

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
                            <tbody>
                            @php($counter=1)
                            @foreach($data ?? [] as $item)
                                <tr wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{$counter}}
                                    </th>
                                    <td>

                                        {{$item->title}}
                                    </td>

                                    <td>
                                        @forelse($item->tags as $tag)
                                            <span class="badge bg-primary-transparent me-1 mb-1">
                                                {{ $tag->name }}
                                            </span>
                                        @empty
                                            <span class="text-muted">—</span>
                                        @endforelse
                                    </td>

                                    <td>
                                        @can('services.edit')
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
                                            @can('services.edit')

                                            <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                    class="ri-edit-line"></i></a>
                                            @endcan
                                                @can('services.delete')

                                            <a  data-bs-toggle="modal" href="#delete" wire:click="get_data({{$item->id}})"  class="text-danger fs-14 lh-1"><i
                                                    class="ri-delete-bin-5-line"></i></a>
                                                @endcan
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

                <form wire:submit="save()">

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

                        <div class="row">

                            <div class="col-xl-6">
                                <label class="form-label">
                                    عنوان {{$info['personal'] ?? ''}}
                                </label>

                                <input
                                    wire:model.lazy="title"
                                    type="text"
                                    class="form-control @error('title') is-invalid @enderror"
                                    placeholder="لطفا عنوان {{$info['personal'] ?? ''}} را وارد کنید">

                                @error('title')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            <div class="col-xl-6">
                                <label class="form-label">
                                    اسلاگ {{$info['personal'] ?? ''}}
                                </label>

                                <input
                                    wire:model.lazy="slug"
                                    type="text"
                                    class="form-control @error('slug') is-invalid @enderror"
                                    placeholder="لطفا اسلاگ {{$info['personal'] ?? ''}} را وارد کنید">

                                @error('slug')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            {{-- دسته بندی --}}

                            {{-- تگ‌ها --}}
                            <div class="col-xl-6 mt-3">
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

                            {{-- تاریخ انتشار --}}


                            {{-- تصویر شاخص --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    تصویر شاخص
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
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    تصویر بنر
                                    {{$info['banner_image'] ?? ''}}
                                </label>

                                <div x-data="{ progress: 0 }"
                                     x-on:livewire-upload-start="progress = 0"
                                     x-on:livewire-upload-finish="progress = 100"
                                     x-on:livewire-upload-error="progress = 0"
                                     x-on:livewire-upload-progress="progress = $event.detail.progress">

                                    <input wire:model.lazy="banner_image"
                                           class="form-control mb-1 @error('banner_image') is-invalid @enderror" type="file" >

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


                            {{-- توضیح کوتاه --}}
                            <div class="col-xl-12 mt-3">
                                <label class="form-label">
                                    توضیح کوتاه
                                </label>

                                <textarea
                                    wire:model.lazy="short_description"
                                    class="form-control @error('short_description') is-invalid @enderror"
                                    rows="3"
                                    placeholder="لطفا توضیح کوتاه {{$info['personal'] ?? ''}} را وارد کنید"></textarea>

                                @error('short_description')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- توضیحات کامل --}}
                            <div class="col-xl-12 mt-3">
                                <label class="form-label">
                                    توضیحات کامل {{$info['personal'] ?? ''}}
                                </label>

                                <div class="@error('description') border border-danger rounded @enderror">
                                    <div wire:ignore>
                                        <div id="editor"></div>
                                    </div>
                                </div>

                                @error('description')
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
                                wire:loading.attr="disabled"
                                wire:target="featured_image,banner_image,save"
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

                        {{-- لودینگ --}}
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
    @push('styles')
        <link href="{{asset('dashboard')}}/libs/quill/quill.snow.css" rel="stylesheet"/>
        <link href="{{asset('dashboard')}}/libs/quill/quill.bubble.css" rel="stylesheet"/>
    @endpush
    @push('scripts')
        <script>
            var toolbarOptions = [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                [{ 'font': [] }],
                ['bold', 'italic', 'underline', 'strike'],        // toggled buttons
                ['blockquote', 'code-block'],

                [{ 'header': 1 }, { 'header': 2 }],               // custom button values
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'script': 'sub' }, { 'script': 'super' }],      // superscript/subscript
                [{ 'indent': '-1' }, { 'indent': '+1' }],          // outdent/indent
                [{ 'direction': 'rtl' }],                         // text direction

                [{ 'size': ['small', false, 'large', 'huge'] }],  // custom dropdown

                [{ 'color': [] }, { 'background': [] }],          // dropdown with defaults from theme
                [{ 'align': [] }],

                ['image', 'video'],
                ['clean']                                         // remove formatting button
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
    @endpush
</div>
