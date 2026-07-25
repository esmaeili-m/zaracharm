<?php

use Livewire\Component;
use \App\Models\ProductVariant;
use \App\Models\Tag;
use \App\Models\Brand;
use \App\Models\Category;
use \App\Models\Product;
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
    public $search;
    public $inventory_id;
    public $quantity;
    public $minimum_quantity;
    public $status;
    public $inventories;
    public $data;
    public $variants;
    public ProductVariant $model;
    public Product $product;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Product $product,ProductVariant $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->product=$product;
        $this->inventories = \App\Models\Inventory::active()->orderBy('sort')->get();
        $this->info['header']='لیست تنوع محصولات';
        $this->info['create']='افزودن کالا';
        $this->info['delete']='حذف کالا';
        $this->info['personal']='تنوع کالا';
        $this->info['table']['headers']=[
            '#',
            'نام',
            'تنوع محصول',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();

    }
    public function loadData()
    {
        $this->data = $this->model::with([
            'product.brand',
            'values.option'
        ])->where('product_id',$this->product->id)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('sku', 'like', "%{$this->search}%")
                        ->orWhere('barcode', 'like', "%{$this->search}%")
                        ->orWhere('price', 'like', "%{$this->search}%");
                });
            })
            ->get();
        foreach($this->data as $variant){

            $this->variants[$variant->id] = [
                'sku'=>$variant->sku,
                'barcode'=>$variant->barcode,
                'price'=>$variant->price,
                'compare_price'=>$variant->compare_price,
                'cost_price'=>$variant->cost_price,
            ];

        }
    }
    public function update_variant($id)
    {



        $this->validate([

            "variants.$id.sku" => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_variants','sku')
                    ->ignore($id)
            ],

            "variants.$id.barcode" => [
                'nullable',
                'string',
                'max:255',
            ],

            "variants.$id.price" => [
                'nullable',
                'integer',
                'min:0'
            ],

            "variants.$id.compare_price" => [
                'nullable',
                'integer',
                'min:0'
            ],

            "variants.$id.cost_price" => [
                'nullable',
                'integer',
                'min:0'
            ],


        ], [

            "variants.$id.sku.unique" =>
                'این کد SKU قبلاً برای یک تنوع دیگر ثبت شده است.',

            "variants.$id.sku.string" =>
                'کد SKU باید به صورت متن وارد شود.',

            "variants.$id.sku.max" =>
                'کد SKU نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',


            "variants.$id.barcode.string" =>
                'بارکد باید به صورت متن وارد شود.',

            "variants.$id.barcode.max" =>
                'بارکد نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',


            "variants.$id.price.integer" =>
                'قیمت فروش باید عدد باشد.',

            "variants.$id.price.min" =>
                'قیمت فروش نمی‌تواند منفی باشد.',


            "variants.$id.compare_price.integer" =>
                'قیمت قبل از تخفیف باید عدد باشد.',

            "variants.$id.compare_price.min" =>
                'قیمت قبل از تخفیف نمی‌تواند منفی باشد.',


            "variants.$id.cost_price.integer" =>
                'قیمت خرید باید عدد باشد.',

            "variants.$id.cost_price.min" =>
                'قیمت خرید نمی‌تواند منفی باشد.',

        ]);



        ProductVariant::where('id',$id)
            ->update([
                'sku'=>$this->variants[$id]['sku'],
                'barcode'=>$this->variants[$id]['barcode'],
                'price'=>$this->variants[$id]['price'],
                'compare_price'=>$this->variants[$id]['compare_price'],
                'cost_price'=>$this->variants[$id]['cost_price'],
            ]);



        $this->dispatch(
            'alert',
            type:'success',
            title:'موفق',
            text:'اطلاعات تنوع محصول ذخیره شد'
        );

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
        $this->selectItem= $this->model->findOrFail($id);
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data','product','inventories');
        }else{
            $this->resetExcept(['selectItem','model','info','data','product','inventories']);
            $this->dispatch('close-modal');
        }
        $this->dispatch('editor-update');

    }

    protected function rules()
    {
        return [

            'inventory_id' => [
                'required',
                'exists:inventories,id',
            ],

            'quantity' => [
                'required',
                'integer',
                'min:0',
            ],

            'minimum_quantity' => [
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

            'inventory_id.required' => 'انتخاب انبار الزامی است.',
            'inventory_id.exists'   => 'انبار انتخاب شده معتبر نیست.',

            'quantity.required' => 'موجودی کالا را وارد کنید.',
            'quantity.integer'  => 'موجودی باید عدد صحیح باشد.',
            'quantity.min'      => 'موجودی نمی‌تواند منفی باشد.',

            'minimum_quantity.required' => 'حداقل موجودی را وارد کنید.',
            'minimum_quantity.integer'  => 'حداقل موجودی باید عدد صحیح باشد.',
            'minimum_quantity.min'      => 'حداقل موجودی نمی‌تواند منفی باشد.',

            'status.required' => 'وضعیت را انتخاب کنید.',
            'status.boolean'  => 'وضعیت انتخاب شده معتبر نیست.',
        ];
    }
    public function set_stock()
    {
        abort_if(!auth()->user()->can('categories.edit'), 403);

        $data = $this->validate();

        \App\Models\InventoryItem::updateOrCreate(

            [
                'inventory_id' => $data['inventory_id'],
                'product_variant_id' => $this->selectItem->id,
            ],

            [
                'quantity' => $data['quantity'],
                'minimum_quantity' => $data['minimum_quantity'],
                'status' => $data['status'],
            ]

        );

        $this->resetData('close');

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'موجودی انبار با موفقیت ذخیره شد.'
        );
    }
    public function updatedInventoryId($inventoryId)
    {
        $stock = \App\Models\InventoryItem::where('inventory_id', $inventoryId)
            ->where('product_variant_id', $this->selectItem->id)
            ->first();

        if ($stock) {

            $this->quantity = $stock->quantity;
            $this->minimum_quantity = $stock->minimum_quantity;
            $this->status = $stock->status;

        } else {

            $this->quantity = 0;
            $this->minimum_quantity = 0;
            $this->status = true;

        }
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

                            <div class="d-flex gap-2 my-3">

                                <img
                                    width="60"
                                    height="60"
                                    style="border-radius:5px"
                                    src="{{$product->FeaturedImageUrl}}"
                                >

                                <div>

                                    <p class="mb-0">
                                        {{$product->title}}
                                    </p>

                                    <small class="text-muted">
                                        برند:
                                        {{$product->brand?->title ?? 'ثبت نشده'}}
                                    </small>

                                </div>

                            </div>


                        <table class="table text-nowrap">

                            <thead>
                            <tr>

                                <th>#</th>
                                <th>ویژگی‌ها</th>
                                <th>SKU</th>
                                <th>بارکد</th>
                                <th>قیمت فروش</th>
                                <th>قیمت قبل تخفیف</th>
                                <th>قیمت خرید</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>

                            </tr>
                            </thead>


                            <tbody>

                            @php($counter = 1)


                            @forelse($this->data ?? [] as $item)

                                <tr wire:key="variant-{{ $item->id }}">


                                    <td>
                                        {{$counter}}
                                    </td>
                                    {{-- ویژگی ها --}}
                                    <td>

                                        @foreach($item->values as $value)

                                            <span class="badge bg-info me-1">
                            {{$value->option?->title}}
                            :
                            {{$value->title}}

                        </span>

                                        @endforeach

                                    </td>





                                    {{-- SKU --}}
                                    <td>

                                        <input
                                            type="text"
                                            class="form-control form-control-sm @error("variants.$item->id.sku") is-invalid @enderror"
                                            wire:model.lazy="variants.{{$item->id}}.sku"
                                            placeholder="SKU"
                                        >


                                        @error("variants.$item->id.sku")
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                        @enderror
                                    </td>





                                    {{-- Barcode --}}
                                    <td>

                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            wire:model.lazy="variants.{{$item->id}}.barcode"
                                            placeholder="بارکد"
                                        >

                                    </td>





                                    {{-- قیمت فروش --}}
                                    <td>

                                        <input
                                            type="number"
                                            class="form-control form-control-sm"
                                            wire:model.lazy="variants.{{$item->id}}.price"
                                            placeholder="قیمت"
                                        >

                                    </td>





                                    {{-- قیمت قبل تخفیف --}}
                                    <td>

                                        <input
                                            type="number"
                                            class="form-control form-control-sm"
                                            wire:model.lazy="variants.{{$item->id}}.compare_price"
                                            placeholder="قبل تخفیف"
                                        >

                                    </td>





                                    {{-- قیمت خرید --}}
                                    <td>

                                        <input
                                            type="number"
                                            class="form-control form-control-sm"
                                            wire:model.lazy="variants.{{$item->id}}.cost_price"
                                            placeholder="خرید"
                                        >

                                    </td>





                                    {{-- وضعیت --}}
                                    <td>

                    <span
                        wire:click="change_status({{$item->id}})"
                        style="cursor:pointer"
                        class="badge bg-outline-{{$item->status ? 'success':'danger'}}"
                    >

                        {{$item->status ? 'فعال':'غیرفعال'}}

                    </span>

                                    </td>





                                    {{-- عملیات --}}
                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            <a style="cursor: pointer"  wire:click="update_variant({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                    class="ri-check-line"></i></a>
                                            <a style="cursor: pointer" data-bs-effect="effect-flip-horizontal" data-bs-toggle="modal" href="#create_inventories" wire:click="get_data({{$item->id}})"
                                               class="text-info fs-14 lh-1"><i class="ri-archive-line"></i></a>

                                        </div>
                                    </td>



                                </tr>


                                @php($counter++)


                            @empty

                                <tr>

                                    <td colspan="10" class="text-center py-5">

                                        موردی وجود ندارد

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

    <div wire:ignore.self class="modal fade" id="create_inventories">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h6 class="modal-title">مدیریت موجودی کالا</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <form wire:submit.prevent="set_stock" id="save_stock">

                        <div class="row">

                            {{-- انبار --}}
                            <div class="col-md-6">
                                <label class="form-label">انبار</label>

                                <select
                                    wire:model.live="inventory_id"
                                    class="form-select @error('inventory_id') is-invalid @enderror">

                                    <option value="">انتخاب انبار</option>

                                    @foreach($inventories as $inventory)
                                        <option value="{{ $inventory->id }}">
                                            {{ $inventory->title }}
                                        </option>
                                    @endforeach

                                </select>

                                @error('inventory_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">موجودی</label>

                                <input
                                    type="number"
                                    min="0"
                                    wire:model.lazy="quantity"
                                    class="form-control @error('quantity') is-invalid @enderror">

                                @error('quantity')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- موجودی رزرو --}}
                            <div class="col-md-4 mt-3">
                                <label class="form-label">موجودی رزرو شده</label>

                                <input
                                    type="number"
                                    min="0"
                                    wire:model.lazy="reserved_quantity"
                                    class="form-control @error('reserved_quantity') is-invalid @enderror">

                                @error('reserved_quantity')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- حداقل موجودی --}}
                            <div class="col-md-4 mt-3">
                                <label class="form-label">حداقل موجودی</label>

                                <input
                                    type="number"
                                    min="0"
                                    wire:model.lazy="minimum_quantity"
                                    class="form-control @error('minimum_quantity') is-invalid @enderror">

                                @error('minimum_quantity')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- وضعیت --}}
                            <div class="col-md-4 mt-3">
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

                    <div wire:loading.remove wire:target="set_stock">

                        <button
                            form="save_stock"
                            type="submit"
                            class="btn btn-info">

                            ذخیره موجودی

                        </button>

                        <button
                            class="btn btn-light"
                            data-bs-dismiss="modal">

                            بستن

                        </button>

                    </div>

                    <div wire:loading
                         wire:target="set_stock"
                         class="spinner-grow text-info">

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
        <script src="{{asset('dashboard/libs/datepicker/jalalidatepicker.min.js')}}"></script>
        <script>
            jalaliDatepicker.startWatch();
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
