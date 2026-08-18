<?php

use Livewire\Component;
use \App\Models\Campaign;
use \App\Models\Product;
use \App\Models\Brand;
use \App\Models\Category;
use \App\Models\CampaignReward;
use Illuminate\Validation\Rule;

new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $reward_type = '';

    public $value;

    public $max_value;

    public $product_id;

    public $products = [];
    public $search;

    public CampaignReward $model;
    public Campaign $campaign;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Campaign $campaign,CampaignReward $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->campaign=$campaign;
        $this->info['header']   = 'پاداش‌های کمپین';
        $this->info['create']   = 'افزودن پاداش';
        $this->info['delete']   = 'حذف پاداش';
        $this->info['personal'] = 'پاداش';

        $this->info['table']['headers'] = [
            '#',
            'نوع پاداش',
            'مقدار',
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
    public function updatedRewardType($value)
    {
        if ((int)$value === 3) {

            $this->products = Product::active()->select('id', 'title')
                ->orderBy('title')
                ->get();

        } else {

            $this->products = [];
            $this->product_id = null;
        }
    }
    public function loadData()
    {
        $this->data = $this->model
            ->where('campaign_id', $this->campaign->id)
            ->get();
    }
    public function get_data($id)
    {
        $this->selectItem = $this->model->findOrFail($id);

        $this->reward_type = $this->selectItem->reward_type;
        $this->value = $this->selectItem->value;
        $this->max_value = $this->selectItem->max_value;
        $this->product_id = $this->selectItem->product_id;


        // اگر پاداش محصول هدیه باشد
        if ((int)$this->reward_type === 3) {

            $this->products = Product::select('id', 'title')
                ->orderBy('title')
                ->get();

        } else {

            $this->products = [];

        }
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data','campaign');
        }else{
            $this->resetExcept(['selectItem','model','info','data','campaign']);
            $this->dispatch('close-modal');
        }
        $this->dispatch('editor-update');

    }
    protected function rules(): array
    {
        return [

            'reward_type' => [
                'required',
                'integer',
                'in:0,1,2,3,4',
            ],

            'value' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'max_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'product_id' => [
                'nullable',
                'exists:products,id',
            ],

        ];
    }
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            switch ((int)$this->reward_type) {

                // درصد تخفیف
                case 0:

                    if ($this->value === null || $this->value === '') {

                        $validator->errors()->add(
                            'value',
                            'وارد کردن درصد تخفیف الزامی است.'
                        );

                    }

                    if ($this->value > 100) {

                        $validator->errors()->add(
                            'value',
                            'درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد.'
                        );

                    }

                    break;



                // مبلغ ثابت
                case 1:

                    if ($this->value === null || $this->value === '') {

                        $validator->errors()->add(
                            'value',
                            'وارد کردن مبلغ تخفیف الزامی است.'
                        );

                    }

                    break;



                // ارسال رایگان
                case 2:

                    break;



                // محصول هدیه
                case 3:

                    if (!$this->product_id) {

                        $validator->errors()->add(
                            'product_id',
                            'انتخاب محصول هدیه الزامی است.'
                        );

                    }

                    break;



                // کش بک
                case 4:

                    if ($this->value === null || $this->value === '') {

                        $validator->errors()->add(
                            'value',
                            'وارد کردن مبلغ کش بک الزامی است.'
                        );

                    }

                    break;

            }

        });
    }
    protected function messages(): array
    {
        return [

            'reward_type.required' => 'انتخاب نوع پاداش الزامی است.',
            'reward_type.integer' => 'نوع پاداش باید معتبر باشد.',
            'reward_type.in' => 'نوع پاداش انتخاب شده معتبر نیست.',


            'value.numeric' => 'مقدار پاداش باید عدد باشد.',
            'value.min' => 'مقدار پاداش نمی‌تواند منفی باشد.',


            'max_value.numeric' => 'سقف تخفیف باید عدد باشد.',
            'max_value.min' => 'سقف تخفیف نمی‌تواند منفی باشد.',


            'product_id.exists' => 'محصول انتخاب شده معتبر نیست.',

        ];
    }
    protected function validationAttributes(): array
    {
        return [

            'reward_type' => 'نوع پاداش',
            'value' => 'مقدار',
            'max_value' => 'سقف تخفیف',
            'product_id' => 'محصول هدیه',

        ];
    }

    public function save(){
        abort_if(!auth()->user()->can('categories.create'), 403);
        $data= $this->validate();
        $data['campaign_id']=$this->campaign->id;
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
                                        {{ $item->reward_type_name }}
                                    </td>

                                    <td>
                                        {{ $item->value_text }}
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
                        <div class="row g-3">

                            {{-- نوع پاداش --}}
                            <div class="col-md-6">

                                <label class="form-label">
                                    نوع پاداش
                                </label>

                                <select
                                    wire:model.live="reward_type"
                                    class="form-select @error('reward_type') is-invalid @enderror">

                                    <option value="">
                                        انتخاب کنید
                                    </option>

                                    <option value="0">
                                        درصد تخفیف
                                    </option>

                                    <option value="1">
                                        مبلغ تخفیف ثابت
                                    </option>

                                    <option value="2">
                                        ارسال رایگان
                                    </option>

                                    <option value="3">
                                        محصول هدیه
                                    </option>

                                    <option value="4">
                                        کش بک
                                    </option>

                                </select>

                                @error('reward_type')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            @if($reward_type !== '')

                                {{-- درصد تخفیف --}}
                                @if((int)$reward_type === 0)

                                    <div class="col-md-6">

                                        <label class="form-label">
                                            درصد تخفیف
                                        </label>

                                        <input
                                            type="number"
                                            min="1"
                                            max="100"
                                            wire:model.lazy="value"
                                            class="form-control @error('value') is-invalid @enderror"
                                            placeholder="مثلاً 20">

                                        @error('value')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                        @enderror

                                    </div>


                                    <div class="col-md-6">

                                        <label class="form-label">
                                            سقف تخفیف (تومان)
                                        </label>

                                        <input
                                            type="number"
                                            min="0"
                                            wire:model.lazy="max_value"
                                            class="form-control @error('max_value') is-invalid @enderror"
                                            placeholder="مثلاً 500000">

                                        @error('max_value')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                        @enderror

                                    </div>

                                @endif



                                {{-- مبلغ ثابت --}}
                                @if((int)$reward_type === 1)

                                    <div class="col-md-6">

                                        <label class="form-label">
                                            مبلغ تخفیف (تومان)
                                        </label>

                                        <input
                                            type="number"
                                            min="0"
                                            wire:model.lazy="value"
                                            class="form-control @error('value') is-invalid @enderror"
                                            placeholder="مثلاً 250000">

                                        @error('value')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                        @enderror

                                    </div>

                                @endif



                                {{-- ارسال رایگان --}}
                                @if((int)$reward_type === 2)

                                    <div class="col-md-12">

                                        <div class="alert alert-success mb-0">

                                            این پاداش نیاز به تنظیمات دیگری ندارد.

                                        </div>

                                    </div>

                                @endif



                                {{-- محصول هدیه --}}
                                @if((int)$reward_type === 3)

                                    <div class="col-md-12">

                                        <label class="form-label">
                                            محصول هدیه
                                        </label>

                                        <select
                                            wire:model="product_id"
                                            class="form-select @error('product_id') is-invalid @enderror">

                                            <option value="">
                                                انتخاب محصول
                                            </option>

                                            @foreach($products as $product)

                                                <option value="{{ $product->id }}">
                                                    {{ $product->title }}
                                                </option>

                                            @endforeach

                                        </select>

                                        @error('product_id')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                        @enderror

                                    </div>

                                @endif



                                {{-- کش بک --}}
                                @if((int)$reward_type === 4)

                                    <div class="col-md-6">

                                        <label class="form-label">
                                            مبلغ کش بک (تومان)
                                        </label>

                                        <input
                                            type="number"
                                            min="0"
                                            wire:model.lazy="value"
                                            class="form-control @error('value') is-invalid @enderror"
                                            placeholder="مثلاً 300000">

                                        @error('value')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                        @enderror

                                    </div>

                                @endif

                            @endif

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

</div>
