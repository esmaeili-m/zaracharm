<?php

use Livewire\Component;
use \App\Models\Campaign;
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
    public $type;
    public $status;
    public $priority;
    public $start_at;
    public $end_at;
    public $settings;
    public $featured_image;
    public $editorImage;
    public $search;

    public Campaign $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Campaign $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->info['header']='لیست کمپین ها';
        $this->info['create']='افزودن کمپین';
        $this->info['delete']='حذف کمپین';
        $this->info['personal']='کمپین';
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

        $this->data = $query->get();
    }
    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->title=$this->selectItem->title;
        $this->slug=$this->selectItem->slug;
        $this->description=$this->selectItem->description;
        $this->type=$this->selectItem->type;
        $this->status=$this->selectItem->status;
        $this->priority=$this->selectItem->priority;
        $this->start_at=$this->selectItem->start_at;
        $this->end_at=$this->selectItem->end_at;
        $this->settings=$this->selectItem->settings;
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
    public function messages()
    {
        return [

            // title
            'title.required' => 'وارد کردن عنوان کمپین الزامی است.',
            'title.string'   => 'عنوان کمپین باید متن باشد.',
            'title.min'      => 'عنوان کمپین باید حداقل ۲ کاراکتر باشد.',
            'title.max'      => 'عنوان کمپین نباید بیشتر از ۲۰۰ کاراکتر باشد.',


            // slug
            'slug.required' => 'وارد کردن اسلاگ الزامی است.',
            'slug.string'   => 'اسلاگ باید متن باشد.',
            'slug.min'      => 'اسلاگ باید حداقل ۳ کاراکتر باشد.',
            'slug.max'      => 'اسلاگ نباید بیشتر از ۲۰۰ کاراکتر باشد.',
            'slug.regex'    => 'اسلاگ فقط می‌تواند شامل حروف فارسی، انگلیسی، اعداد، خط تیره (-) و زیرخط (_) باشد.',
            'slug.unique'   => 'این اسلاگ قبلاً ثبت شده است.',


            // description
            'description.string' => 'توضیحات کمپین باید متن باشد.',


            // type
            'type.required' => 'انتخاب نوع کمپین الزامی است.',
            'type.integer'  => 'نوع کمپین نامعتبر است.',
            'type.between'  => 'نوع کمپین انتخاب شده معتبر نیست.',


            // status
            'status.required' => 'انتخاب وضعیت کمپین الزامی است.',
            'status.integer'  => 'وضعیت کمپین نامعتبر است.',
            'status.between'  => 'وضعیت کمپین انتخاب شده معتبر نیست.',


            // priority
            'priority.integer' => 'اولویت باید عدد باشد.',
            'priority.min'     => 'اولویت نمی‌تواند منفی باشد.',


            // dates
            'start_at.date' => 'تاریخ شروع کمپین معتبر نیست.',

            'end_at.date' => 'تاریخ پایان کمپین معتبر نیست.',
            'end_at.after_or_equal' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.',

            // settings
            'settings.array' => 'تنظیمات کمپین باید به صورت آرایه ارسال شود.',
        ];
    }
    public function rules()
    {
        $campaignId = $this->selectItem?->id;

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
                Rule::unique('campaigns', 'slug')->ignore($campaignId),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'type' => [
                'required',
                'integer',
                'between:0,4',
            ],

            'status' => [
                'required',
                'integer',
                'between:0,3',
            ],

            'priority' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'start_at' => [
                'nullable',
                'date',
            ],

            'end_at' => [
                'nullable',
                'date',
                'after_or_equal:start_at',
            ],
            'settings' => [
                'nullable',
                'array',
            ],
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
        $this->loadData();
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
                                                @can('categories.view')

                                                    <a href="{{route('campaign.targets',$item->id)}}"  class="text-warning fs-14 lh-1"><i
                                                            class="ri-list-radio"></i></a>
                                                @endcan
                                                @can('categories.view')

                                                    <a href="{{route('campaign.conditions',$item->id)}}"  class="text-success fs-14 lh-1"><i
                                                            class="ri-list-check"></i></a>
                                                @endcan
                                                @can('categories.view')
                                                    <a href="{{route('campaign.rewards',$item->id)}}"  class="text-success fs-14 lh-1"><i
                                                            class="ri-currency-line"></i></a>
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
                            <div class="col-xl-4">
                                <label class="form-label">عنوان کمپین</label>
                                <input
                                    wire:model.lazy="title"
                                    type="text"
                                    class="form-control @error('title') is-invalid @enderror"
                                    placeholder="عنوان کمپین را وارد کنید">

                                @error('title')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>


                            {{-- اسلاگ --}}
                            <div class="col-xl-4">
                                <label class="form-label">آدرس کمپین</label>
                                <input
                                    wire:model.lazy="slug"
                                    type="text"
                                    class="form-control @error('slug') is-invalid @enderror"
                                    placeholder="آدرس کمپین را وارد کنید">

                                @error('slug')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>


                            {{-- نوع کمپین --}}
                            <div class="col-xl-4">
                                <label class="form-label">نوع کمپین</label>

                                <select
                                    wire:model.lazy="type"
                                    class="form-select @error('type') is-invalid @enderror">

                                    <option value="">انتخاب کنید</option>

                                    <option value="0">تخفیف</option>
                                    <option value="1">فروش ویژه</option>
                                    <option value="2">ارسال رایگان</option>
                                    <option value="3">هدیه</option>
                                    <option value="4">خرید X دریافت Y</option>

                                </select>

                                @error('type')
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

                                    <option value="0">پیش‌نویس</option>
                                    <option value="1">فعال</option>
                                    <option value="2">منقضی شده</option>
                                    <option value="3">غیرفعال</option>

                                </select>

                                @error('status')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>


                            {{-- اولویت --}}
                            <div class="col-xl-4 mt-3">
                                <label class="form-label">اولویت نمایش</label>

                                <input
                                    wire:model.lazy="priority"
                                    type="number"
                                    class="form-control @error('priority') is-invalid @enderror"
                                    placeholder="اولویت">

                                @error('priority')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>


                            {{-- تاریخ شروع --}}
                            <div class="col-xl-4 mt-3">

                                <label class="form-label">
                                    تاریخ شروع
                                </label>

                                <input
                                    wire:model.lazy="start_at"
                                    data-jdp
                                    class="form-control @error('start_at') is-invalid @enderror">

                                @error('start_at')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            {{-- تاریخ پایان --}}
                            <div class="col-xl-4 mt-3">

                                <label class="form-label">
                                    تاریخ پایان
                                </label>

                                <input
                                    wire:model.lazy="end_at"
                                    data-jdp

                                    class="form-control @error('end_at') is-invalid @enderror">

                                @error('end_at')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            {{-- بنر --}}
                            <div class="col-xl-4 mt-3">

                                <label class="form-label">
                                    تصویر بنر کمپین
                                </label>

                                <input
                                    wire:model.lazy="featured_image"
                                    type="file"
                                    class="form-control @error('featured_image') is-invalid @enderror">


                                @error('featured_image')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            {{-- توضیحات --}}
                            <div class="col-xl-12 mt-3">

                                <label class="form-label">
                                    توضیحات کمپین
                                </label>

                                <div wire:ignore>
                                    <div id="editor"></div>
                                </div>


                                @error('description')
                                <div class="invalid-feedback d-block">
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
        <link rel="stylesheet" href="{{asset('dashboard/libs/datepicker/jalalidatepicker.min.css')}}">

        <link href="{{asset('dashboard')}}/libs/quill/quill.snow.css" rel="stylesheet"/>
        <link href="{{asset('dashboard')}}/libs/quill/quill.bubble.css" rel="stylesheet"/>
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

    @endpush
</div>
