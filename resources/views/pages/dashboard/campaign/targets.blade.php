<?php

use Livewire\Component;
use \App\Models\Campaign;
use \App\Models\Product;
use \App\Models\Brand;
use \App\Models\Category;
use \App\Models\CampaignTarget;
use Illuminate\Validation\Rule;
use App\Enums\CampaignTargetType;
new class extends Component
{

    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $target_type;
    public $target_id;
    public $products = [];
    public $categories = [];
    public $brands = [];
    public $targets  = [];
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

    public CampaignTarget $model;
    public Campaign $campaign;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Campaign $campaign,CampaignTarget $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->campaign=$campaign;
        $this->products=\App\Models\Product::active()->orderBy('title')->pluck('title','id');
        $this->categories=\App\Models\Category::active()->orderBy('title')->pluck('title','id');
        $this->brands=\App\Models\Brand::active()->orderBy('title')->pluck('title','id');
        $this->info['header']   = 'اهداف کمپین';
        $this->info['create']   = 'افزودن هدف';
        $this->info['delete']   = 'حذف هدف';
        $this->info['personal'] = 'هدف';
        $this->info['table']['headers']=[
            '#',
            'نوع هدف',
            'هدف',
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
        $this->data = $this->model
            ->where('campaign_id', $this->campaign->id)
            ->get();
    }
    public function get_data($id)
    {
        $this->selectItem = $this->model->findOrFail($id);

        $this->target_type = $this->selectItem->target_type;

        $this->target_id = $this->selectItem->target_id ?? [];

        $this->updatedTargetType();
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
            'target_type' => [
                'required',
                Rule::enum(CampaignTargetType::class),
            ],

            'target_id' => [
                'nullable',
                'integer',
                'required_unless:target_type,' . CampaignTargetType::ALL->value,
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'target_type.required' =>
                'انتخاب نوع هدف الزامی است.',

            'target_type.enum' =>
                'نوع هدف انتخاب شده معتبر نیست.',

            'target_id.required_unless' =>
                'انتخاب هدف الزامی است.',

            'target_id.integer' =>
                'هدف انتخاب شده معتبر نیست.',
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
    public function updatedTargetType(): void
    {
        $this->target_id = null;

        $this->targets = match ((int) $this->target_type) {

            0 => collect(), // کل فروشگاه

            1 => Product::select('id', 'title')
                ->get(),

            2 => Category::select('id', 'title')
                ->get(),

            3 => Brand::select('id', 'title')
                ->get(),

            default => collect(),

        };
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
                                        {{$item->target_type_name  }}
                                    </td>
                                    <td>
                                        {{$item->target_name  }}
                                    </td>

                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
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

                            {{-- نوع هدف --}}
                            <div class="col-md-6">

                                <label class="form-label">
                                    نوع هدف
                                </label>

                                <select
                                    wire:model.live="target_type"
                                    class="form-select"
                                >

                                    <option value="0">
                                        کل فروشگاه
                                    </option>

                                    <option value="1">
                                        محصول
                                    </option>

                                    <option value="2">
                                        دسته‌بندی
                                    </option>

                                    <option value="3">
                                        برند
                                    </option>

                                </select>

                                @error('target_type')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>

                            {{-- هدف --}}
                            <div class="col-md-6">

                                <label class="form-label">
                                    انتخاب هدف
                                </label>

                                <select
                                    wire:model="target_id"
                                    class="form-select @error('target_id') is-invalid @enderror">

                                    <option value="">
                                        انتخاب کنید
                                    </option>

                                    @foreach($targets ?? [] as $item)

                                        <option value="{{ $item->id }}">
                                            {{ $item->title ?? $item->name }}
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

</div>
