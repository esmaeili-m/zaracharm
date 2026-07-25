<?php

use Livewire\Component;
use \App\Models\ProductOption;
use \App\Models\Product;
use \App\Models\Option;
use \App\Models\ProductVariant;
use \App\Models\ProductVariantOptionValue;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $model;
    public $search;
    public $option_id;
    public $product_id;
    public $is_required;
    public $options;
    public $option_values=[];
    public ProductOption $productOption;
    public Product $product;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Product $product,ProductOption $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->product=$product;
        $this->options=Option::active()->orderBy('title')->pluck('title','id');
        $this->info['header']='لیست ویژگی محصول';
        $this->info['create']='افزودن ویژگی محصول';
        $this->info['delete']='حذف ویژگی محصول';
        $this->info['personal']='ویژگی محصول';
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
    private function variantExists($values)
    {
        return ProductVariant::where('product_id',$this->product->id)
            ->whereHas('optionValues', function($query) use($values){

                $query->whereIn('option_values.id',$values);

            }, '=', count($values))
            ->exists();
    }
//    public function create_variants()
//    {
//        foreach ($this->option_values ?? [] as $item){
//            foreach ($item as $key => $value){
//                $variant = \App\Models\ProductVariant::create([
//                    'product_id' =>$this->product->id,
//                ]);
//                \App\Models\ProductVariantOptionValue::create([
//                    'product_variant_id' =>$variant->id,
//                    'option_value_id' =>$key,
//                ]);
//            }
//
//        }
//
//    }
    private function cartesian($arrays)
    {

        $result = [[]];


        foreach($arrays as $array){

            $temp = [];


            foreach($result as $resultItem){

                foreach($array as $item){

                    $temp[] = array_merge(
                        $resultItem,
                        [$item]
                    );

                }

            }


            $result = $temp;

        }


        return $result;

    }
    public function create_variants()
    {

        $this->validate(
            [
                'option_values' => 'required|array|min:1',
                'option_values.*' => 'array|min:1',
                'option_values.*.*' => 'boolean',
            ],
            [
                'option_values.required' => 'لطفاً حداقل یک ویژگی برای محصول انتخاب کنید.',
                'option_values.array' => 'فرمت ویژگی‌های انتخاب شده صحیح نیست.',
                'option_values.min' => 'حداقل یک ویژگی باید انتخاب شود.',

                'option_values.*.array' => 'مقادیر ویژگی باید به صورت صحیح ارسال شوند.',
                'option_values.*.min' => 'برای هر ویژگی حداقل یک مقدار انتخاب کنید.',

                'option_values.*.*.boolean' => 'مقدار انتخاب شده نامعتبر است.',
            ]
        );

        $selectedOptions = [];

        foreach ($this->option_values as $optionId => $values) {

            $selectedValues = [];

            foreach ($values as $valueId => $checked) {

                if ($checked) {
                    $selectedValues[] = $valueId;
                }

            }
            if(count($selectedValues)){

                $selectedOptions[] = $selectedValues;

            }

        }


        if(empty($selectedOptions)){

            $this->addError(
                'option_values',
                'حداقل یک ویژگی انتخاب کنید'
            );

            return;

        }

        $combinations = $this->cartesian($selectedOptions);
        DB::transaction(function () use ($combinations){
            foreach($combinations as $combination){
                $exists = ProductVariant::where('product_id',$this->product->id)
                    ->whereHas('optionValues',function($q) use($combination){
                        $q->whereIn(
                            'option_value_id',
                            $combination
                        );
                    }, '=', count($combination))
                    ->exists();
                if($exists){
                    continue;
                }

                $variant = ProductVariant::create([
                    'product_id'=>$this->product->id,
                    'status'=>true
                ]);

                foreach($combination as $valueId){

                    ProductVariantOptionValue::create([

                        'product_variant_id'=>$variant->id,

                        'option_value_id'=>$valueId

                    ]);

                }


            }


        });



        $this->dispatch(
            'alert',
            type:'success',
            title:'عملیات موفق',
            text:'تنوع محصولات با موفقیت ساخته شد'
        );

    }
    public function loadData()
    {
        $query = $this->model->where('product_id',$this->product->id)->with('option',function ($query) {
            $query->where('title', 'LIKE', '%' . $this->search . '%');
        });

        $this->data = $query->orderBy('sort')->get();
    }
    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->product_id=$this->selectItem->title;
        $this->slug=$this->selectItem->slug;
        $this->dispatch('editor-update');
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data','product','options');
        }else{
            $this->resetExcept(['selectItem','model','info','data','product','options']);
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
            'option_id' => [
                'required',
                 Rule::unique('options', 'slug')->ignore($categoryId),
            ],
        ];
    }

    public function messages()
    {
        return [

            // title
            'option_id.required' => 'وارد کردن ویژگی الزامی است.',
        ];
    }

    public function save(){
        abort_if(!auth()->user()->can('categories.create'), 403);
        $data= $this->validate();
        $data['product_id']=$this->product->id;
        $data['is_required']=$this->is_required ?? 0;
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
            @can('categories.create')
                <button wire:click="create_variants()"  class="btn btn-info-light btn-wave me-0">
                    <i class="ri-refresh-fill align-middle">
                    </i>
                    همگام سازی
                </button>
            @endcan
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
                                        {{$item->option?->title}}
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

                                                <a data-bs-toggle="modal" href="#select_option_form" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
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
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                     {{ $info['personal'] ?? '' }}
                                </label>

                                <select
                                    wire:model.lazy="option_id"
                                    class="form-select @error('option_id') is-invalid @enderror">

                                    <option value="">
                                        ویژگی محصول را انتخاب کنید
                                    </option>

                                    @foreach($options ?? [] as $value => $title)
                                        <option value="{{ $value }}">
                                            {{ $title }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('option_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                       اجبار بودن{{ $info['personal'] ?? '' }}
                                </label>

                                <select
                                    wire:model.lazy="is_required"
                                    class="form-select @error('is_required') is-invalid @enderror">

                                    <option value="">
                                        اجبار را انتخاب کنید
                                    </option>

                                        <option value="0">غیر اجباری</option>
                                        <option value="1">اجباری</option>
                                </select>

                                @error('is_required')
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
    <div wire:ignore.self class="modal fade" id="select_option_form">
        <div class="modal-dialog modal-dialog-centered text-center modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">انتخاب ویژگی محصول</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="select_option()"  id="select_option">
                        <div class="row">
                            <div class="col-xl-12 mt-3">
                                <label class="form-label">
                                     انتخاب تنوع وِیژگی {{$selectItem?->option->title}}
                                </label>
                                <div class="row mt-2">
                                    @foreach($selectItem?->option?->values ?? [] as $value)
                                        <div class="col-3">
                                            <div class="form-check">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="option_{{ $value->id }}"
                                                    wire:model.live="option_values.{{$selectItem?->option->id}}.{{$value->id}}"
                                                    value="{{ $value->id }}">

                                                <label class="form-check-label" for="option_{{ $value->id }}">
                                                    {{ $value->title }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach

                                </div>


                                @error('option_values')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                    </form>

                </div>
                <div class="modal-footer">

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
