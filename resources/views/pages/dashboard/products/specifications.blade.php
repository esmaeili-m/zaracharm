<?php

use Livewire\Component;
use \App\Models\ProductSpecification;
use \App\Models\Product;
use \App\Models\Option;
use \App\Models\ProductVariant;
use \App\Models\ProductVariantOptionValue;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use App\Models\Specification;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public array $selectedSpecifications = [];
    public array $productSpecifications = [];
    public $selectItem;
    public $data;
    public $model;
    public $search;
    public $specifications_value;
    public $option_id;
    public $allSpecifications;
    public $product_id;
    public $is_required;
    public $options;
    public $option_values=[];
    public ProductSpecification $productOption;
    public Product $product;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Product $product,ProductSpecification $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->product=$product;
        $this->options=Option::active()->orderBy('title')->pluck('title','id');
        $this->info['header']='لیست مشخصه فنی محصول';
        $this->info['create']='افزودن مشخصه فنی محصول';
        $this->info['delete']='حذف مشخصه فنی محصول';
        $this->info['personal']='مشخصه فنی محصول';
        $this->info['table']['headers']=[
            '#',
            'نام',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();

    }

    public function select_specification()
    {
        $this->validate([
            'productSpecifications' => [
                'required',
                'array',
                'min:1'
            ],

            'productSpecifications.*' => [
                'integer',
                'exists:specifications,id'
            ],

        ], [

            'productSpecifications.required' =>
                'حداقل یک مشخصه فنی را انتخاب کنید.',

            'productSpecifications.min' =>
                'حداقل یک مشخصه فنی را انتخاب کنید.',

            'productSpecifications.*.exists' =>
                'یکی از مشخصات انتخاب شده معتبر نیست.',

        ]);


        $this->product
            ->specifications()
            ->syncWithoutDetaching($this->productSpecifications);

        $this->loadData();
        $this->resetData('close');

        $this->dispatch(
            'alert',
            type:'success',
            title: 'عملیات موفق',
            message:'مشخصات فنی محصول با موفقیت ذخیره شد.'
        );
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
        $this->selectedSpecifications = $this->product
            ->specifications()
            ->pluck('specifications.id')
            ->toArray();


        $this->allSpecifications = Specification::query()
            ->whereNotIn('id',$this->selectedSpecifications)
            ->where('status',true)
            ->orderBy('sort')
            ->get();


        $this->data = $this->model
            ->where('product_id',$this->product->id)
            ->when($this->search,function($query){

                $query->whereHas('specification',function($q){

                    $q->where('title','LIKE','%'.$this->search.'%');

                });

            })
            ->with('specification')
            ->get();
    }
    public function get_data($id)
    {
        $this->selectItem = $this->model
            ->with('specification')
            ->findOrFail($id);


        $specification = $this->selectItem->specification;


        $this->specifications_value = match($specification->type){

            // Text
            1 => $this->selectItem->text_value,


            // Number
            2 => $this->selectItem->number_value,


            // Decimal
            3 => $this->selectItem->decimal_value,


            // Boolean
            4 => $this->selectItem->boolean_value,


            // Date
            5 => $this->selectItem->date_value,


            default => null,

        };
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data','product','selectedSpecifications');
        }else{
            $this->resetExcept(['selectItem','model','info','data','product','selectedSpecifications']);
            $this->dispatch('close-modal');
        }
        $this->dispatch('editor-update');

    }


    public function updatedTitle()
    {
        $this->slug = preg_replace('/\s+/', '-', trim($this->title));
    }


    private function getValidationRule($specification)
    {
        return match ($specification->type) {

            // Text
            1 => [
                'required',
                'string',
                'max:255'
            ],


            // Number
            2 => [
                'required',
                'integer',
                'min:0'
            ],


            // Decimal
            3 => [
                'required',
                'numeric',
                'min:0'
            ],


            // Boolean
            4 => [
                'required',
                'boolean'
            ],


            // Date
            5 => [
                'required',
                'date'
            ],


            default => [
                'nullable'
            ],

        };
    }
    public function save()
    {
        abort_if(!auth()->user()->can('categories.create'), 403);


        $specification = $this->selectItem?->specification;


        if (!$specification) {
            return;
        }



        $this->validate([

            'specifications_value' =>
                $this->getValidationRule($specification),

        ], [


            'specifications_value.required' =>
                "مقدار {$specification->title} الزامی است.",


            'specifications_value.integer' =>
                "مقدار {$specification->title} باید عدد صحیح باشد.",


            'specifications_value.numeric' =>
                "مقدار {$specification->title} باید عدد باشد.",


            'specifications_value.date' =>
                "تاریخ {$specification->title} معتبر نیست.",


        ]);

        $data = [

            'product_id' => $this->product->id,

            'specification_id' => $specification->id,

        ];



        // مقداردهی بر اساس نوع مشخصه

        switch ($specification->type) {


            // Text
            case 1:

                $data['text_value'] = $this->specifications_value;

                break;



            // Number
            case 2:

                $data['number_value'] = $this->specifications_value;

                break;



            // Decimal
            case 3:

                $data['decimal_value'] = $this->specifications_value;

                break;



            // Boolean
            case 4:

                $data['boolean_value'] = $this->specifications_value;

                break;



            // Date
            case 5:

                $data['date_value'] = $this->specifications_value;

                break;

        }

        $item = $this->selectItem

            ? tap($this->selectItem)->update($data)

            : $this->model->create($data);




        $this->loadData();


        $this->resetData('close');



        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',

            text: $this->selectItem

                ? 'مقدار مشخصه با موفقیت ویرایش شد.'

                : 'مقدار مشخصه با موفقیت ثبت شد.',
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
                <button wire:click="resetData()" data-bs-toggle="modal" href="#select_specifications_form"  class="btn btn-success-light btn-wave me-0">
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
                    @error('option_values')
                    <div class="alert alert-danger" role="alert">
                        {{$message}}
                    </div>
                    @enderror
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
                                        {{$item->specification?->title}}
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
        <div class="modal-dialog modal-dialog-centered text-center modal-sm modal-dialog-scrollable" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{$info['create']}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="save" id="save">

                        <div class="row">

                            @if($selectItem?->specification)

                                <div class="col-xl-12 mt-3">

                                    <label class="form-label">
                                        {{ $selectItem->specification->title }}
                                    </label>


                                    {{-- Text --}}
                                    @if($selectItem->specification->type == 1)

                                        <input
                                            type="text"
                                            class="form-control @error('specifications_value') is-invalid @enderror"
                                            wire:model.lazy="specifications_value">

                                    @endif



                                    {{-- Number --}}
                                    @if($selectItem->specification->type == 2)

                                        <input
                                            type="number"
                                            class="form-control @error('specifications_value') is-invalid @enderror"
                                            wire:model.lazy="specifications_value">

                                    @endif



                                    {{-- Decimal --}}
                                    @if($selectItem->specification->type == 3)

                                        <input
                                            type="number"
                                            step="0.01"
                                            class="form-control @error('specifications_value') is-invalid @enderror"
                                            wire:model.lazy="specifications_value">

                                    @endif



                                    {{-- Boolean --}}
                                    @if($selectItem->specification->type == 4)

                                        <select
                                            class="form-select @error('specifications_value') is-invalid @enderror"
                                            wire:model.lazy="specifications_value">

                                            <option value="">
                                                انتخاب کنید
                                            </option>

                                            <option value="1">
                                                بله
                                            </option>

                                            <option value="0">
                                                خیر
                                            </option>

                                        </select>

                                    @endif



                                    {{-- Date --}}
                                    @if($selectItem->specification->type == 5)

                                        <input
                                            type="date"
                                            class="form-control @error('specifications_value') is-invalid @enderror"
                                            wire:model.lazy="specifications_value">

                                    @endif



                                    @error('specifications_value')

                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>

                                    @enderror


                                </div>

                            @endif

                        </div>

                    </form>

                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="save">
                        <button class="btn btn-info"
                                form="save"
                                wire:loading.attr="disabled"
                                wire:target="save"
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
    <div wire:ignore.self class="modal fade" id="select_specifications_form">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">

                <div class="modal-header">
                    <h6 class="modal-title">
                        انتخاب مشخصات فنی محصول
                    </h6>

                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <form
                        id="select_specification"
                        wire:submit.prevent="select_specification">

                        <div class="mb-3">

                            <label class="form-label">
                                مشخصات فنی مورد نیاز محصول
                            </label>

                            <div wire:ignore>

                                <select
                                    id="specifications"
                                    wire:model="productSpecifications"
                                    class="form-select @error('productSpecifications') is-invalid @enderror"
                                    multiple>

                                    @foreach($allSpecifications ?? [] as $specification)

                                        <option
                                            value="{{ $specification->id }}">

                                            {{ $specification->title }}

                                        </option>

                                    @endforeach

                                </select>

                            </div>

                            @error('productSpecifications')
                            <div class="invalid-feedback d-block">
                                {{ $message }}
                            </div>
                            @enderror

                        </div>

                    </form>

                </div>

                <div class="modal-footer">
                    <div wire:loading.remove wire:target="select_specification">
                        <button class="btn btn-info"
                                form="select_specification"
                                wire:loading.attr="disabled"
                                wire:target="select_specification"
                                type="submit">
                            ذخیره تغییرات
                        </button>
                        <button class="btn btn-light" data-bs-dismiss="modal" type="button">
                            بستن
                        </button>
                    </div>

                    <!-- اسپینر لودینگ Livewire -->
                    <div wire:loading wire:target="select_specification" class="spinner-grow text-info" role="status">
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
