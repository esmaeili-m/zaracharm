<?php

use Livewire\Component;
use \App\Models\Tag;
use \App\Services\FileUploadService;
use Illuminate\Validation\Rule;
use App\Models\Course;
use Livewire\Attributes\On;
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

    public $price = 0;

    public $discount_price = null;

    public $is_free = false;

    public $level = '';

    public $duration = 0;

    public $status = true;

    public $category_id = null;

    public $published_at = null;

    public $featured_image;

    public $banner_image;

    public $search;

    public $editorImage;

    public Course $model;

    public $tag_ids = [];

    #[On('update-description')]
    public function updateDescription($payload=null)
    {
        $this->description = $payload['value'];
    }
    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Course $model)
    {
        abort_if(!auth()->user()->can('courses.view'), 403);

        $this->model=$model;
        $this->info['header']='لیست دوره ها';
        $this->info['create']='افزودن دوره';
        $this->info['delete']='حذف دوره';
        $this->info['table']['headers']=[
            '#',
            'نام دوره',
            'قیمت',
            'دسته بندی',
            'تاریخ انتشار',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('courses.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'دوره با موفقیت ویرایش شد.'
        );
        $this->loadData();
    }
    public function delete()
    {
        abort_if(!auth()->user()->can('courses.delete'), 403);

        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->loadData();
            $this->resetData('close');
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text:  'دوره با موفقیت حذف شد.'
            );
        }

    }
    public function loadData()
    {
        abort_if(!auth()->user()->can('courses.view'), 403);

        $query = $this->model->query();
        if ($this->search) {
            $query->where('title', 'LIKE' ,'%'.$this->search.'%')
                ->orWhere('description', 'LIKE' ,'%'.$this->search.'%')
                ->orWhere('short_description', 'LIKE' ,'%'.$this->search.'%');
        }
        $this->data = $query->orderBy('sort')->get();

    }
    #[\Livewire\Attributes\On('updateOrder')]
    public function updateOrder($ids)
    {
        abort_if(!auth()->user()->can('courses.edit'), 403);

        foreach ($ids as $index => $id) {
            $this->model->where('id', $id)->update([
                'sort' => $index + 1
            ]);
        }
        $this->loadData();
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text:  'دوره با موفقیت ویرایش شد.'
        );
    }

    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->title=$this->selectItem->title;
        $this->slug=$this->selectItem->slug;
        $this->description=$this->selectItem->description;
        $this->short_description=$this->selectItem->short_description;
        $this->price=$this->selectItem->price;
        $this->discount_price=$this->selectItem->discount_price;
        $this->is_free=$this->selectItem->is_free;
        $this->level=$this->selectItem->level;
        $this->duration=$this->selectItem->duration;
        $this->status=$this->selectItem->status;
        $this->category_id=$this->selectItem->category_id;
        $this->published_at=$this->selectItem->published_at;
        $this->featured_image=$this->selectItem->featured_image;
        $this->banner_image=$this->selectItem->banner_image;
        $this->tag_ids = $this->selectItem->tags()->pluck('tags.id')->toArray();
        $this->dispatch('editor-update');



    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
        }else{
            $this->resetExcept(['model','info','data']);
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


    protected function rules(): array
    {
        return [

            'title' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],

            'slug' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('courses', 'slug')->ignore($this->selectItem?->id),
            ],

            'description' => [
                'required',
                'string',
            ],

            'short_description' => [
                'required',
                'string',
                'max:1000',
            ],

            'price' => [
                'required',
                'integer',
                'min:0',
            ],

            'discount_price' => [
                'nullable',
                'integer',
                'min:0',
                'lte:price',
            ],

            'is_free' => [
                'required',
                'boolean',
            ],

            'level' => [
                'nullable',
                Rule::in([
                    'beginner',
                    'intermediate',
                    'advanced',
                ]),
            ],

            'duration' => [
                'required',
                'integer',
                'min:0',
            ],

            'status' => [
                'required',
                'boolean',
            ],

            'category_id' => [
                'required',
                'exists:categories,id',
            ],

            'published_at' => [
                'nullable',
                'date',
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
                $this->selectItem ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'banner_image' => [
                $this->selectItem ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],

        ];
    }

    protected function messages(): array
    {
        return [

            // title
            'title.required' => 'عنوان دوره الزامی است.',
            'title.string'   => 'عنوان دوره معتبر نیست.',
            'title.min'      => 'عنوان دوره باید حداقل ۳ کاراکتر باشد.',
            'title.max'      => 'عنوان دوره نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',

            // slug
            'slug.required'   => 'اسلاگ دوره الزامی است.',
            'slug.string'     => 'اسلاگ وارد شده معتبر نیست.',
            'slug.min'        => 'اسلاگ باید حداقل ۳ کاراکتر باشد.',
            'slug.max'        => 'اسلاگ نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'slug.alpha_dash' => 'اسلاگ فقط می‌تواند شامل حروف انگلیسی، عدد و خط تیره باشد.',
            'slug.unique'     => 'این اسلاگ قبلاً ثبت شده است.',

            // description
            'description.required' => 'توضیحات دوره الزامی است.',
            'description.string'   => 'توضیحات دوره معتبر نیست.',

            // short_description
            'short_description.required' => 'توضیح کوتاه دوره الزامی است.',
            'short_description.string'   => 'توضیح کوتاه معتبر نیست.',
            'short_description.max'      => 'توضیح کوتاه نمی‌تواند بیشتر از ۱۰۰۰ کاراکتر باشد.',

            // price
            'price.required' => 'قیمت دوره الزامی است.',
            'price.integer'  => 'قیمت دوره باید عدد باشد.',
            'price.min'      => 'قیمت دوره نمی‌تواند کمتر از صفر باشد.',

            // discount_price
            'discount_price.integer' => 'قیمت تخفیف باید عدد باشد.',
            'discount_price.min'     => 'قیمت تخفیف نمی‌تواند کمتر از صفر باشد.',
            'discount_price.lte'     => 'قیمت تخفیف نباید بیشتر از قیمت اصلی باشد.',

            // is_free
            'is_free.required' => 'وضعیت رایگان بودن دوره الزامی است.',
            'is_free.boolean'  => 'وضعیت رایگان بودن دوره معتبر نیست.',

            // level
            'level.in' => 'سطح انتخاب شده معتبر نیست.',

            // duration
            'duration.required' => 'مدت زمان دوره الزامی است.',
            'duration.integer'  => 'مدت زمان دوره باید عدد باشد.',
            'duration.min'      => 'مدت زمان دوره نمی‌تواند کمتر از صفر باشد.',

            // status
            'status.required' => 'وضعیت دوره الزامی است.',
            'status.boolean'  => 'وضعیت دوره معتبر نیست.',

            // category
            'category_id.required' => 'انتخاب دسته‌بندی الزامی است.',
            'category_id.exists'   => 'دسته‌بندی انتخاب شده معتبر نیست.',

            // published_at
            'published_at.date' => 'تاریخ انتشار معتبر نیست.',

            // featured_image
            'featured_image.required' => 'تصویر شاخص الزامی است.',
            'featured_image.image'    => 'فایل تصویر شاخص معتبر نیست.',
            'featured_image.mimes'    => 'تصویر باید jpg, jpeg, png یا webp باشد.',
            'featured_image.max'      => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',

            // banner_image
            'banner_image.required' => 'بنر دوره الزامی است.',
            'banner_image.image'    => 'فایل بنر معتبر نیست.',
            'banner_image.mimes'    => 'بنر باید jpg, jpeg, png یا webp باشد.',
            'banner_image.max'      => 'حجم بنر نباید بیشتر از ۴ مگابایت باشد.',

            // tags
            'tag_ids.array'       => 'تگ‌ها باید به صورت لیست باشند.',
            'tag_ids.*.integer'   => 'شناسه تگ معتبر نیست.',
            'tag_ids.*.exists'    => 'یکی از تگ‌های انتخاب شده وجود ندارد.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [

            'title' => 'عنوان دوره',
            'slug' => 'اسلاگ',
            'description' => 'توضیحات کامل',
            'short_description' => 'توضیح کوتاه',
            'price' => 'قیمت',
            'discount_price' => 'قیمت تخفیف',
            'is_free' => 'رایگان بودن',
            'level' => 'سطح دوره',
            'duration' => 'مدت زمان',
            'status' => 'وضعیت',
            'category_id' => 'دسته بندی',
            'published_at' => 'تاریخ انتشار',
            'featured_image' => 'تصویر شاخص',
            'banner_image' => 'بنر دوره',

        ];
    }

    public function save()
    {
        abort_if(!auth()->user()->can('courses.create'), 403);

        $data = $this->validate();
        // رایگان بودن دوره
        if ($data['is_free']) {
            $data['price'] = 0;

            $data['discount_price'] = null;
        }

        // نویسنده دوره
        $data['user_id'] = auth()->id() ?? 1;
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        unset($data['featured_image']);
        unset($data['banner_image']);
        // ساخت یا ویرایش
        $course = $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);

        $course->tags()->sync($tagIds);

        /*
        |--------------------------------------------------------------------------
        | تصویر شاخص
        |--------------------------------------------------------------------------
        */

        if ($this->featured_image) {

            $course->media()
                ->where('collection', 'featured_image')
                ->delete();

            $this->upload(
                $this->featured_image,
                $course,
                'featured_image'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | تصویر بنر
        |--------------------------------------------------------------------------
        */

        if ($this->banner_image) {

            $course->media()
                ->where('collection', 'banner_image')
                ->delete();

            $this->upload(
                $this->banner_image,
                $course,
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
                ? 'دوره با موفقیت ویرایش شد.'
                : 'دوره جدید با موفقیت ایجاد شد.'
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
            <a href="{{route('courses.trash')}}" class="btn btn-warning-light btn-wave me-2">
                <i class="bx bx-trash align-middle">
                </i>
                سطل آشغال
            </a>
            <button wire:click="resetData()" data-bs-effect="effect-flip-horizontal" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave me-0">
                <i class="ri-add-line align-middle">
                </i>
                {{$info['create']}}
            </button>

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
                        <div class="autoComplete_wrapper" role="combobox" aria-owns="autoComplete_list_1" aria-haspopup="true" aria-expanded="false"><input wire:model.laz="search" autocapitalize="none" autocomplete="off" class="header-search-bar form-control" id="header-search" placeholder="جستجو برای نتایج..." spellcheck="false" type="text" aria-controls="autoComplete_list_1" aria-autocomplete="both"><ul id="autoComplete_list_1" role="listbox" hidden=""></ul></div>
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
                            <tbody id="simple-list">
                            @php($counter=1)
                            @foreach($data ?? [] as $item)
                                <tr data-id="{{ $item->id }}" wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{$counter}}
                                    </th>
                                    <td>

                                            {{$item->title}}
                                    </td>
                                    <td>
                                        {{$item->price}}
                                    </td>
                                    <td>
                                        {{$item->category?->name}}
                                    </td>
                                    <td>
                                        {{$item->published_at}}
                                    </td>
                                    <td>
                                     <span style="cursor: pointer" wire:click="change_status({{$item->id}})"
                                           wire:loading.attr="disabled"
                                           class="badge bg-outline-{{$item->status == 1 ? 'success' : 'danger'}}">
                                            <span wire:target="change_status" wire:loading.remove>{{$item->status == 1 ? 'فعال' : 'غیرفعال'}}</span>
                                            <span wire:target="change_status" wire:loading>در حال تغییر...</span>
                                     </span>
                                    </td>
                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                    class="ri-edit-line"></i></a>
                                            <a href="{{ route('courses.sections', $item) }}" class="text-primary fs-14 lh-1" title="مدیریت فصل"><i
                                                    class="ri-play-list-line"></i></a>
                                            <a  data-bs-toggle="modal" href="#delete" wire:click="get_data({{$item->id}})"  class="text-danger fs-14 lh-1"><i
                                                    class="ri-delete-bin-5-line"></i></a>
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

                            {{-- عنوان دوره --}}
                            <div class="col-xl-6">
                                <label class="form-label">
                                    عنوان دوره
                                </label>

                                <input
                                    wire:model.lazy="title"
                                    type="text"
                                    class="form-control @error('title') is-invalid @enderror"
                                    placeholder="لطفا عنوان دوره را وارد کنید">

                                @error('title')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- اسلاگ --}}
                            <div class="col-xl-6">
                                <label class="form-label">
                                    اسلاگ دوره
                                </label>

                                <input
                                    wire:model.lazy="slug"
                                    type="text"
                                    class="form-control @error('slug') is-invalid @enderror"
                                    placeholder="لطفا اسلاگ دوره را وارد کنید">

                                @error('slug')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- قیمت --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    قیمت دوره
                                </label>

                                <input
                                    wire:model.lazy="price"
                                    type="number"
                                    class="form-control @error('price') is-invalid @enderror"
                                    placeholder="لطفا قیمت دوره را وارد کنید">

                                @error('price')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- قیمت تخفیف --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    قیمت با تخفیف
                                </label>

                                <input
                                    wire:model.lazy="discount_price"
                                    type="number"
                                    class="form-control @error('discount_price') is-invalid @enderror"
                                    placeholder="لطفا قیمت تخفیف را وارد کنید">

                                @error('discount_price')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- نوع دوره --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    نوع دوره
                                </label>

                                <select
                                    wire:model.lazy="is_free"
                                    class="form-control @error('is_free') is-invalid @enderror">

                                    <option value="0">
                                        پولی
                                    </option>

                                    <option value="1">
                                        رایگان
                                    </option>

                                </select>

                                @error('is_free')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- سطح --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    سطح دوره
                                </label>

                                <select
                                    wire:model.lazy="level"
                                    class="form-control @error('level') is-invalid @enderror">

                                    <option value="">
                                        سطح دوره را انتخاب کنید
                                    </option>

                                    <option value="beginner">
                                        مبتدی
                                    </option>

                                    <option value="intermediate">
                                        متوسط
                                    </option>

                                    <option value="advanced">
                                        پیشرفته
                                    </option>

                                </select>

                                @error('level')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- مدت زمان --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    مدت زمان دوره
                                </label>

                                <input
                                    wire:model.lazy="duration"
                                    type="number"
                                    class="form-control @error('duration') is-invalid @enderror"
                                    placeholder="مدت زمان دوره به ساعت">

                                @error('duration')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- دسته بندی --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    دسته بندی
                                </label>

                                <select
                                    wire:model.lazy="category_id"
                                    class="form-control @error('category_id') is-invalid @enderror">

                                    <option value="">
                                        دسته بندی را انتخاب کنید
                                    </option>

                                    @foreach(\App\Models\Category::pluck('name','id') as $key => $category)
                                        <option value="{{ $key }}">
                                            {{ $category }}
                                        </option>
                                    @endforeach

                                </select>

                                @error('category_id')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>


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


                            {{-- وضعیت --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    وضعیت دوره
                                </label>

                                <select
                                    wire:model.lazy="status"
                                    class="form-control @error('status') is-invalid @enderror">

                                    <option value="1">
                                        فعال
                                    </option>

                                    <option value="0">
                                        غیرفعال
                                    </option>

                                </select>

                                @error('status')
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
                            <div class="col-xl-6 mt-3">
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

                            {{-- توضیح کوتاه --}}
                            <div class="col-xl-12 mt-3">
                                <label class="form-label">
                                    توضیح کوتاه
                                </label>

                                <textarea
                                    wire:model.lazy="short_description"
                                    class="form-control @error('short_description') is-invalid @enderror"
                                    rows="3"
                                    placeholder="لطفا توضیح کوتاه دوره را وارد کنید"></textarea>

                                @error('short_description')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- توضیحات کامل --}}
                            <div class="col-xl-12 mt-3">
                                <label class="form-label">
                                    توضیحات کامل دوره
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
        <div class="modal-dialog modal-dialog-centered text-center modal-lg" role="document">
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

    @push('scripts')
        <script src="{{asset('dashboard/libs/datepicker/jalalidatepicker.min.js')}}"></script>
        <script>
            jalaliDatepicker.startWatch();
        </script>
        <script>
            const toolbarOptions = [
                [{ header: [1, 2, 3, 4, 5, 6, false] }],
                [{ font: [] }],
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote', 'code-block'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ script: 'sub' }, { script: 'super' }],
                [{ indent: '-1' }, { indent: '+1' }],
                [{ direction: 'rtl' }],
                [{ size: ['small', false, 'large', 'huge'] }],
                [{ color: [] }, { background: [] }],
                [{ align: [] }],
                ['image', 'video'],
                ['clean']
            ];

            async function uploadToLivewire(file) {
                return new Promise((resolve, reject) => {

                @this.upload(
                    'editorImage',
                    file,

                    async (uploadedFilename) => {

                        try {
                            const url = await @this.call('saveEditorImage');
                            resolve(url);
                        } catch (e) {
                            reject(e);
                        }

                    },

                    (error) => {
                        reject(error);
                    },

                    (event) => {
                        console.log(event.detail.progress + '%');
                    }
                );

                });
            }

            function imageHandler() {

                const input = document.createElement('input');
                input.setAttribute('type', 'file');
                input.setAttribute('accept', 'image/*');

                input.click();

                input.onchange = async () => {

                    const file = input.files[0];

                    if (!file) return;

                    const range = quill.getSelection(true);

                    const loadingText = 'در حال آپلود...';

                    quill.insertText(range.index, loadingText, {
                        italic: true,
                        color: '#999'
                    });

                    quill.disable();

                    try {

                        const url = await uploadToLivewire(file);

                        quill.enable();

                        quill.deleteText(range.index, loadingText.length);

                        quill.insertEmbed(range.index, 'image', url);

                        quill.setSelection(range.index + 1);

                    } catch (e) {

                        console.error(e);

                        quill.enable();

                        quill.deleteText(range.index, loadingText.length);

                        alert('خطا در آپلود تصویر');

                    }

                };
            }

            const quill = new Quill('#editor', {

                theme: 'snow',

                modules: {
                    toolbar: {
                        container: toolbarOptions,
                        handlers: {
                            image: imageHandler
                        }
                    }
                }

            });

            quill.on('text-change', function () {

            @this.set('description', quill.root.innerHTML);

            });

            Livewire.on('editor-update', () => {

                const html = @this.get('description');

                if (quill.root.innerHTML !== html) {
                    quill.root.innerHTML = html;
                }

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
    @push('styles')
        <link rel="stylesheet" href="{{asset('dashboard/libs/datepicker/jalalidatepicker.min.css')}}">
        <link href="{{asset('dashboard')}}/libs/quill/quill.snow.css" rel="stylesheet"/>
        <link href="{{asset('dashboard')}}/libs/quill/quill.bubble.css" rel="stylesheet"/>
    @endpush
</div>
