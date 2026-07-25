<?php

use Livewire\Component;
use \App\Models\Product;
use \App\Models\Tag;
use \App\Models\Brand;
use \App\Models\Category;
use \App\Enums\ProductType;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';
    public $info=[];
    public array $gallery = [];
    public $selectItem;
    public $title='';
    public $slug;
    public $sort=1;
    public $published_at = null;
    public $description;
    public $brand_id;
    public $short_description;
    public $type=1;
    public $featured_image;
    public $banner_image;
    public $editorImage;
    public $search;
    public $tags;
    public $categories;
    public $brands;
    public $tag_ids = [];
    public $category_ids = [];

    public Product $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Product $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->tags = Tag::orderBy('title')->pluck('title', 'id');
        $this->categories = Category::active()->orderBy('sort')->pluck('title', 'id');
        $this->brands = Brand::active()->orderBy('sort')->pluck('title', 'id');
        $this->info['header']='لیست کالا';
        $this->info['create']='افزودن کالا';
        $this->info['delete']='حذف کالا';
        $this->info['personal']='کالا';
        $this->info['table']['headers']=[
            '#',
            'نام',
            'دسته بندی ها',
            'وضعیت',
            'عملیات',
        ];

    }
    #[Computed]
    public function data()
    {
        return $this->model
            ->where(function ($query) {
                $query->where('title', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            })
            ->orderBy('sort')
            ->paginate(20);
    }
    public function change_status($id)
    {
        abort_if(!auth()->user()->can('categories.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);

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

            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: $this->info['personal']." موفقیت حذف شد."
            );

            $this->resetData('close');
        }

    }


    public function get_data($id)
    {
        $this->selectItem= $this->model->with('media')->findOrFail($id);
        $this->title=$this->selectItem->title;
        $this->slug=$this->selectItem->slug;
        $this->published_at=$this->selectItem->published_at;
        $this->description=$this->selectItem->description;
        $this->brand_id=$this->selectItem->brand_id;
        $this->type=$this->selectItem->type;
        $this->short_description=$this->selectItem->short_description;
        $this->tag_ids = $this->selectItem->tags()->pluck('tags.id')->toArray();
        $this->category_ids = $this->selectItem->categories()->pluck('categories.id')->toArray();
        $this->dispatch('editor-update');
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data','tags','categories','brands');
        }else{
            $this->resetExcept(['selectItem','model','info','data','tags','categories','brands']);
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
        $productId = $this->selectItem?->id;

        return [

            'brand_id' => [
                'nullable',
                'exists:brands,id',
            ],

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
                Rule::unique('products', 'slug')->ignore($productId),
            ],

            'short_description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'type' => [
                'required',
                Rule::enum(ProductType::class),
            ],

            'sort' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'published_at' => [
                'nullable',
                'date',
            ],

            'featured_image' => [
                $this->selectItem ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],


            'category_ids' => [
                'nullable',
                'array',
            ],

            'category_ids.*' => [
                'exists:categories,id',
            ],

            'tag_ids' => [
                'nullable',
                'array',
            ],

            'tag_ids.*' => [
                'exists:tags,id',
            ],

        ];
    }

    public function messages()
    {
        return [

            // Brand
            'brand_id.exists' => 'برند انتخاب شده معتبر نیست.',

            // Title
            'title.required' => 'عنوان محصول الزامی است.',
            'title.string'   => 'عنوان محصول باید متن باشد.',
            'title.min'      => 'عنوان محصول باید حداقل ۲ کاراکتر باشد.',
            'title.max'      => 'عنوان محصول نباید بیشتر از ۲۰۰ کاراکتر باشد.',

            // Slug
            'slug.required' => 'اسلاگ الزامی است.',
            'slug.string'   => 'اسلاگ باید متن باشد.',
            'slug.min'      => 'اسلاگ باید حداقل ۳ کاراکتر باشد.',
            'slug.max'      => 'اسلاگ نباید بیشتر از ۲۰۰ کاراکتر باشد.',
            'slug.regex'    => 'اسلاگ فقط می‌تواند شامل حروف فارسی، انگلیسی، اعداد، خط تیره و زیرخط باشد.',
            'slug.unique'   => 'این اسلاگ قبلاً ثبت شده است.',

            // Short Description
            'short_description.string' => 'توضیح کوتاه باید متن باشد.',
            'short_description.max'    => 'توضیح کوتاه نباید بیشتر از ۱۰۰۰ کاراکتر باشد.',

            // Description
            'description.string' => 'توضیحات باید متن باشد.',

            // Type
            'type.required' => 'نوع محصول را انتخاب کنید.',
            'type.enum'     => 'نوع محصول معتبر نیست.',

            // Sort
            'sort.integer' => 'ترتیب نمایش باید عدد باشد.',
            'sort.min'     => 'ترتیب نمایش نمی‌تواند منفی باشد.',

            // Published At
            'published_at.date' => 'تاریخ انتشار معتبر نیست.',

            // Thumbnail
            'featured_image.required' => 'تصویر شاخص محصول الزامی است.',
            'featured_image.image'    => 'فایل انتخاب شده باید تصویر باشد.',
            'featured_image.mimes'    => 'تصویر شاخص باید jpg، jpeg، png یا webp باشد.',
            'featured_image.max'      => 'حجم تصویر شاخص نباید بیشتر از ۲ مگابایت باشد.',



            // Categories
            'category_ids.array' => 'دسته‌بندی‌ها معتبر نیستند.',
            'category_ids.*.exists' => 'یکی از دسته‌بندی‌های انتخاب شده معتبر نیست.',

            // Tags
            'tag_ids.array' => 'تگ‌ها معتبر نیستند.',
            'tag_ids.*.exists' => 'یکی از تگ‌های انتخاب شده معتبر نیست.',

        ];
    }

    public function save(){
        abort_if(!auth()->user()->can('categories.create'), 403);
        $data= $this->validate();
        $tagIds = $data['tag_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['featured_image']);
        unset($data['tag_ids']);
        unset($data['category_ids']);

        $item = $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);
        $item->tags()->sync($tagIds);
        $item->categories()->sync($categoryIds);

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

    }
    protected function galleryRules(): array
    {
        return [
            'gallery' => [
                'required',
                'array',
                'min:1',
            ],

            'gallery.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],
        ];
    }
    protected function galleryMessages(): array
    {
        return [

            'gallery.required' => 'حداقل یک تصویر انتخاب کنید.',

            'gallery.array' => 'فرمت تصاویر نامعتبر است.',

            'gallery.min' => 'حداقل یک تصویر انتخاب کنید.',

            'gallery.*.image' => 'فایل انتخاب شده تصویر نیست.',

            'gallery.*.mimes' => 'فرمت تصویر باید jpg، jpeg، png یا webp باشد.',

            'gallery.*.max' => 'حجم هر تصویر نباید بیشتر از ۴ مگابایت باشد.',

        ];
    }
    public function saveGallery()
    {
        $this->validate(
            $this->galleryRules(),
            $this->galleryMessages()
        );

        foreach ($this->gallery as $image) {

            $this->upload(
                $image,
                $this->selectItem,
                'gallery'
            );
        }

        $this->resetData('close');
        $this->dispatch(
            'toast',
            type: 'success',
            message: 'گالری محصول با موفقیت ذخیره شد.'
        );
    }
    public function deleteMediaGallery($id)
    {
        $media = \App\Models\Media::findOrFail($id);
        $this->deleteMedia($media);
        $this->dispatch(
            'toast',
            type: 'success',
            message: 'تصویر حذف شد.'
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
                            @forelse($this->data ?? [] as $item)
                                <tr data-id="{{ $item->id }}" wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{$counter}}
                                    </th>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <img  width="40px" height="40px" style="box-shadow: rgba(50, 50, 93, 0.25) 0px 6px 12px -2px, rgba(0, 0, 0, 0.3) 0px 3px 7px -3px;border-radius: 5px" src="{{$item->FeaturedImageUrl }}">
                                            <div class="info">
                                                <p class="mb-0">{{$item->title}}</p>
                                                <p class="text-muted">برند کالا: {{$item->brand?->title ?? 'ثبت نشده'}} </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @foreach($item->categories as $category)
                                            <span class="badge bg-info">
                                                {{ $category->title }}
                                            </span>
                                        @endforeach
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
                                            @can('categories.create')

                                                <a data-bs-toggle="modal" href="#gallery" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                        class="ri-gallery-fill"></i></a>
                                            @endcan
                                            @can('categories.create')

                                                <a href="{{route('products.settings',$item->id)}}" class="text-info fs-14 lh-1"><i
                                                        class="ri-list-settings-fill"></i></a>
                                            @endcan
                                            @can('categories.create')

                                                    <a href="{{route('products.specifications',$item->id)}}" class="text-info fs-14 lh-1">
                                                        <i class="ri-file-list-3-line"></i></a>
                                             @endcan
                                            @can('categories.create')

                                                <a href="{{route('products.prices',$item->id)}}" class="text-warning fs-14 lh-1"><i
                                                        class="ri-money-dollar-box-line"></i></a>
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
                            <div class="col-xl-4 ">
                                <label class="form-label">
                                    نوع {{ $info['personal'] ?? '' }}
                                </label>

                                <select
                                    wire:model.lazy="type"
                                    class="form-select @error('type') is-invalid @enderror">

                                    <option value="">
                                        نوع محصول را انتخاب کنید
                                    </option>

                                    @foreach(\App\Enums\ProductType::options() as $value => $title)
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

                            <div class="col-xl-3 mt-3">
                                <label  for="input-rounded" class="form-label ">جایگاه {{$info['personal'] ?? ''}}</label>
                                <input wire:model.lazy="sort" type="number" class="form-control @error('sort') is-invalid @enderror" id="input-rounded" placeholder="لطفا آدرس {{$info['personal'] ?? ''}} را وارد کنید">
                                @error('sort')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-3 mt-3">
                                <label class="form-label">
                                    تاریخ انتشار
                                </label>

                                <input
                                    wire:model.lazy="published_at"
                                    data-jdp
                                    class="form-control @error('published_at') is-invalid @enderror">

                                @error('published_at')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-3 mt-3">
                                <label class="form-label">
                                    برند {{ $info['personal'] ?? '' }}
                                </label>

                                <select
                                    wire:model.lazy="brand_id"
                                    class="form-select @error('brand_id') is-invalid @enderror">

                                    <option value="">
                                        برند محصول را انتخاب کنید
                                    </option>

                                    @foreach($brands ?? [] as $value => $title)
                                        <option value="{{ $value }}">
                                            {{ $title }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('brand_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            <div class="col-xl-3 mt-3">
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
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    تگ‌ها
                                </label>

                                <select
                                    wire:model.lazy="tag_ids"
                                    multiple
                                    size="4"
                                    class="form-control @error('tag_ids') is-invalid @enderror @error('tag_ids.*') is-invalid @enderror">

                                    @foreach($tags ?? [] as $id => $t)
                                        <option value="{{ $id }}">
                                            {{ $t }}
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
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    دسته بندی ها
                                </label>

                                <select
                                    wire:model.lazy="category_ids"
                                    multiple
                                    size="4"
                                    class="form-control @error('category_ids') is-invalid @enderror @error('category_ids.*') is-invalid @enderror">

                                    @foreach($categories ?? [] as $i => $c)
                                        <option value="{{ $i }}">
                                            {{ $c }}
                                        </option>
                                    @endforeach

                                </select>

                                <small class="text-muted">
                                    برای انتخاب چند تگ، Ctrl را نگه دارید و کلیک کنید.
                                </small>

                                @error('category_ids')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                                @error('category_ids.*')
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
    <div wire:ignore.self class="modal fade" id="gallery">
        <div class="modal-dialog modal-dialog-centered text-center modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{$info['create']}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="saveGallery" id="gallery-form">

                        <div class="row">

                            <div class="col-12">

                                <label class="form-label">
                                    تصاویر گالری
                                </label>

                                <div
                                    x-data="{ progress:0 }"
                                    x-on:livewire-upload-start="progress=0"
                                    x-on:livewire-upload-finish="progress=100"
                                    x-on:livewire-upload-error="progress=0"
                                    x-on:livewire-upload-progress="progress=$event.detail.progress">

                                    <input
                                        wire:model="gallery"
                                        multiple
                                        type="file"
                                        class="form-control
                    @error('gallery') is-invalid @enderror
                    @error('gallery.*') is-invalid @enderror">

                                    <div
                                        class="progress mt-2"
                                        x-show="progress > 0">

                                        <div
                                            class="progress-bar"
                                            :style="'width:'+progress+'%'">

                                            <span x-text="progress+'%'"></span>

                                        </div>

                                    </div>

                                </div>

                                @error('gallery')

                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>

                                @enderror

                                @error('gallery.*')

                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>

                                @enderror

                            </div>

                        </div>

                    </form>
                    <div class="row mt-4">
                        @forelse(($selectItem?->media?->where('collection', 'gallery')) ?? collect() as $media)
                            <div
                                class="col-xl-2 col-lg-3 col-md-4 col-6 mb-4"
                                wire:key="media-{{ $media->id }}">

                                <div class="gallery-item">

                                    <img
                                        src="{{ asset('storage/'.$media->file_path) }}"
                                        alt=""
                                        class="gallery-image">

                                    <button
                                        type="button"
                                        class="gallery-remove"
                                        wire:click="deleteMediaGallery({{ $media->id }})"
                                        wire:confirm="آیا از حذف این تصویر مطمئن هستید؟">

                                        <i class="ri-close-line"></i>

                                    </button>

                                </div>

                            </div>

                        @empty

                            <div class="col-12">

                                <div class="alert alert-light text-center">

                                    تصویری برای این محصول ثبت نشده است.

                                </div>

                            </div>

                        @endforelse

                    </div>


                </div>
                <div class="modal-footer">

                    <div wire:loading.remove wire:target="saveGallery">

                        <button
                            class="btn btn-info"
                            form="gallery-form"
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="gallery,saveGallery">

                            ذخیره تصاویر

                        </button>

                        <button
                            class="btn btn-light"
                            data-bs-dismiss="modal"
                            type="button">

                            بستن

                        </button>

                    </div>

                    <div
                        wire:loading
                        wire:target="gallery,saveGallery"
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
    @push('styles')
        <link rel="stylesheet" href="{{asset('dashboard/libs/datepicker/jalalidatepicker.min.css')}}">
        <link href="{{asset('dashboard')}}/libs/quill/quill.snow.css" rel="stylesheet"/>
        <link href="{{asset('dashboard')}}/libs/quill/quill.bubble.css" rel="stylesheet"/>
        <style>
            .gallery-item{
                position: relative;
                overflow: hidden;
                border-radius: 12px;
                border: 1px solid #e9ecef;
                background: #fff;
                transition: .25s;
            }

            .gallery-item:hover{
                box-shadow: 0 8px 20px rgba(0,0,0,.12);
                transform: translateY(-3px);
            }

            .gallery-image{
                width:100%;
                height:180px;
                object-fit:cover;
                display:block;
            }

            .gallery-remove{

                position:absolute;

                top:8px;

                right:8px;

                width:34px;

                height:34px;

                border:none;

                border-radius:50%;

                background:#dc3545;

                color:#fff;

                display:flex;

                align-items:center;

                justify-content:center;

                cursor:pointer;

                opacity:0;

                transition:.2s;

            }

            .gallery-remove i{
                font-size:18px;
            }

            .gallery-item:hover .gallery-remove{
                opacity:1;
            }
        </style>
    @endpush
    @push('scripts')
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
