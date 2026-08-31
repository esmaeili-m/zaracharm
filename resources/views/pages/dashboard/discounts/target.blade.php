<?php

use Livewire\Component;
use \App\Models\Discount;
use \App\Models\DiscountTarget;
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
    public $discount_id;

    public $target_type;

    public $target_id;

    public $targets = [];
    public $status = true;
    public $search;
    public Discount $discount;
    public DiscountTarget $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Discount $discount,DiscountTarget $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->discount=$discount;
        $this->info['header']='لیست اقلام تخفیف خورده';
        $this->info['create']='افزودن تخفیف';
        $this->info['delete']='حذف تخفیف';
        $this->info['personal']='تخفیف';
        $this->info['table']['headers'] = [
            '#',
            'تخفیف',
            'نوع هدف',
            'عنوان مورد',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();

    }
    public function updatedTargetType($value)
    {
        $this->target_id = null;

        $this->targets = match ($value) {

            'product' => \App\Models\Product::query()->get(),

            'category' => \App\Models\Category::query()->get(),

            'brand' => \App\Models\Brand::query()->get(),

            default => collect(),

        };
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

        // تبدیل نوع ذخیره شده به مقدار فرم
        $this->target_type = match ($this->selectItem->target_type) {

            \App\Models\Product::class => 'product',

            \App\Models\Category::class => 'category',

            \App\Models\Brand::class => 'brand',

            default => null,
        };

        $this->target_id = $this->selectItem->target_id;

        // لود کردن لیست تارگت‌ها برای select
        $this->loadTargets();
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data','discount');
        }else{
            $this->resetExcept(['selectItem','model','info','data','discount']);
            $this->dispatch('close-modal');
        }

    }
    public function loadTargets()
    {
        $this->targets = match ($this->target_type) {

            'product' => \App\Models\Product::query()
                ->select('id','title')
                ->get(),

            'category' => \App\Models\Category::query()
                ->select('id','title')
                ->get(),

            'brand' => \App\Models\Brand::query()
                ->select('id','title')
                ->get(),

            default => collect(),

        };
    }
    public function rules()
    {
        return [

            'target_type' => [
                'required',
                'in:product,category,brand',
            ],

            'target_id' => [
                'required',
                'integer',
            ],

        ];
    }

    public function messages()
    {
        return [

            // target_type
            'target_type.required' => 'نوع هدف تخفیف را انتخاب کنید.',
            'target_type.string'   => 'نوع هدف تخفیف باید متن باشد.',
            'target_type.in'       => 'نوع هدف انتخاب شده معتبر نیست.',


            // target_id
            'target_id.required' => 'انتخاب مورد برای اعمال تخفیف الزامی است.',
            'target_id.integer'  => 'شناسه مورد انتخاب شده معتبر نیست.',

        ];
    }

    public function save(){
        abort_if(!auth()->user()->can('categories.create'), 403);
        $data= $this->validate();
        $data['discount_id']= $this->discount->id;
        $data['target_type'] = match ($this->target_type) {
            'product' => \App\Models\Product::class,
            'category' => \App\Models\Category::class,
            'brand' => \App\Models\Brand::class,
        };
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

                                <tr wire:key="{{ $item->id }}">

                                    <th>
                                        {{ $counter }}
                                    </th>


                                    {{-- نام تخفیف --}}
                                    <td>
                                        {{ $this->discount?->title }}
                                    </td>


                                    {{-- نوع تارگت --}}
                                    <td>

                                        @if($item->target_type == \App\Models\Product::class)

                                            <span class="badge bg-info">
                محصول
            </span>


                                        @elseif($item->target_type == \App\Models\Category::class)

                                            <span class="badge bg-warning">
                دسته بندی
            </span>


                                        @elseif($item->target_type == \App\Models\Brand::class)

                                            <span class="badge bg-primary">
                برند
            </span>

                                        @endif

                                    </td>



                                    {{-- عنوان مورد --}}
                                    <td>

                                        @if($item->target)

                                            {{ $item->target->title }}

                                        @else

                                            <span class="text-danger">
                حذف شده
            </span>

                                        @endif

                                    </td>



                                    {{-- وضعیت تخفیف --}}
                                    <td>

                                        @if($this->discount->status)

                                            <span class="badge bg-outline-success">
                فعال
            </span>

                                        @else

                                            <span class="badge bg-outline-danger">
                غیرفعال
            </span>

                                        @endif

                                    </td>



                                    {{-- عملیات --}}
                                    <td>

                                        <div class="hstack gap-2 flex-wrap">


                                            @can('categories.delete')

                                                <a
                                                    data-bs-toggle="modal"
                                                    href="#delete"
                                                    wire:click="get_data({{ $item->id }})"
                                                    class="text-danger fs-14">

                                                    <i class="ri-delete-bin-5-line"></i>

                                                </a>

                                            @endcan


                                        </div>


                                    </td>


                                </tr>


                                @php($counter++)


                            @empty


                                <tr>

                                    <td colspan="6" class="text-center py-5 text-muted">

                                        <i class="ri-inbox-line fs-1 d-block mb-2"></i>

                                        <strong>
                                            هنوز موردی برای این تخفیف ثبت نشده است.
                                        </strong>


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

                            <div class="col-xl-6">
                                <label class="form-label">
                                    نوع اعمال تخفیف
                                </label>

                                <select
                                    wire:model.live="target_type"
                                    class="form-select @error('target_type') is-invalid @enderror">

                                    <option value="">
                                        انتخاب کنید
                                    </option>

                                    <option value="product">
                                        محصول
                                    </option>

                                    <option value="category">
                                        دسته‌بندی
                                    </option>

                                    <option value="brand">
                                        برند
                                    </option>

                                </select>

                                @error('target_type')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            {{-- انتخاب آیتم --}}
                            <div class="col-xl-6">

                                <label class="form-label">
                                    انتخاب مورد
                                </label>

                                <select
                                    wire:model.lazy="target_id"
                                    class="form-select @error('target_id') is-invalid @enderror">

                                    <option value="">
                                        انتخاب کنید
                                    </option>

                                    @foreach($targets ?? [] as $target)

                                        <option value="{{ $target->id }}">
                                            {{ $target->title }}
                                        </option>

                                    @endforeach

                                </select>


                                @error('target_id')
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

    @endpush
</div>
