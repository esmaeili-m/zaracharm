<?php

use Livewire\Component;
use \App\Models\MenuItem;
use \App\Models\Menu;
use \App\Models\Service;
use \App\Models\Page;
use \App\Models\Course;
use \App\Models\Category;
new class extends Component
{

    public $info=[];
    public $selectItem;
    public $data;
    public $view_type;
    public $name;
    public $menu_id= null;
    public $parent_id= null;
    public $title;
    public $type;
    public $badge;
    public $sort= 1;
    public $reference_id;
    public $url;
    public $menu;
    public $references= [];
    public $types=[
        'page'=> 'صفحه',
        'course'=> 'دوره',
        'category'=> 'دسته بندی',
        'category_list'=> 'لیست دسته بندی ها',
        'external'=> 'لینک خارجی',
    ];

    public $search;
    public $ids;
    public MenuItem $model;
    public Menu $menuModel;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount($id,Menu $menu)
    {
        abort_if(!auth()->user()->can('menus.view'), 403);

        $this->menu = $menu;
        $this->ids = $id;
        $this->model = new MenuItem();
        $this->info['header']='لیست ایتم';
        $this->info['create']='افزودن ایتم';
        $this->info['delete']='حذف ایتم';
        $this->info['personal']='ایتم';
        $this->info['table']['headers']=[
            '#',
            'عنوان',
            'نوع',
            'والد',
            'عملیات',
        ];
        $this->loadData();
    }


    public function delete()
    {
        abort_if(!auth()->user()->can('menus.delete'), 403);

        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->loadData();
            $this->resetData('close');
        }

    }
    public function loadData()
    {
        $this->data = $this->menu
            ->with([
                'children.parent' => function ($query) {
                    $query->where('title', 'LIKE', '%' . $this->search . '%');
                }
            ])
            ->find($this->ids);
    }

    public function updatedType($value)
    {
        $this->reference_id = null;

        if ($value === 'page') {
            $this->references = Page::select('id', 'title')->get();
            if ($this->selectItem){
                $this->reference_id=$this->selectItem->reference_id;
            }
        }

        elseif ($value === 'course') {
            $this->references = Course::select('id', 'title')->get();
        }

        elseif ($value === 'category') {
            $this->references = Category::select('id', 'title')->get();
        }
        elseif ($value === 'service') {
            $this->references = Service::select('id', 'title')->get();
        }

        else {
            $this->references = [];
        }
    }
    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->name=$this->selectItem->name;
        $this->menu_id=$this->selectItem->menu_id;
        $this->parent_id=$this->selectItem->parent_id;
        $this->title=$this->selectItem->title;
        $this->view_type=$this->selectItem->view_type;
        $this->type=$this->selectItem->type;
        $this->updatedType($this->type);
        $this->reference_id=$this->selectItem->reference_id;
        $this->sort=$this->selectItem->sort;
        $this->badge=$this->selectItem->badge;
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data','ids','menu');
        }else{
            $this->resetExcept(['selectItem','model','info','data','ids','menu']);
            $this->dispatch('close-modal');

        }
    }

    public function rules(): array
    {
        return [

            'parent_id' => ['nullable', 'exists:menu_items,id'],

            'title' => ['required', 'string', 'max:255'],

            'type' => ['required'],
            'view_type' => ['nullable'],

            'reference_id' => [
                'nullable',
                'integer',
                'required_if:type,page,course,category,service',
                'prohibited_if:type,category_group,external,course_list,services',
            ],

            'url' => [
                'nullable',
                'url',
                'required_if:type,external',
                'prohibited_unless:type,external',
            ],

            'sort' => ['nullable', 'integer', 'min:0'],
            'badge' => ['nullable'],

        ];
    }

    public function messages(): array
    {
        return [
            'menu_id.required' => 'انتخاب منو الزامی است.',
            'menu_id.exists' => 'منوی انتخاب شده معتبر نیست.',

            'parent_id.exists' => 'آیتم والد معتبر نیست.',

            'title.required' => 'عنوان آیتم منو الزامی است.',
            'title.string' => 'عنوان باید متنی باشد.',
            'title.max' => 'عنوان نباید بیشتر از ۲۵۵ کاراکتر باشد.',

            'type.required' => 'نوع آیتم منو الزامی است.',
            'type.in' => 'نوع آیتم منو نامعتبر است.',

            'reference_id.required_if' => 'انتخاب محتوا الزامی است.',
            'reference_id.integer' => 'مقدار انتخاب محتوا معتبر نیست.',
            'reference_id.prohibited_if' => 'برای این نوع آیتم نیازی به انتخاب محتوا نیست.',

            'url.required_if' => 'لینک خارجی الزامی است.',
            'url.url' => 'فرمت لینک صحیح نیست.',
            'url.prohibited_unless' => 'این فیلد فقط برای لینک خارجی استفاده می‌شود.',

            'sort.integer' => 'ترتیب باید عدد باشد.',
            'sort.min' => 'ترتیب نمی‌تواند منفی باشد.',

        ];
    }
    public function save()
    {
        abort_if(!auth()->user()->can('menus.create'), 403);

        $data = $this->validate();
        $data['menu_id']=$this->ids;
        $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);

        $this->loadData();

        $this->resetData('close');

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $this->selectItem
                ? 'منو با موفقیت ویرایش شد.'
                : 'منو جدید با موفقیت ایجاد شد.'
        );
    }

    #[\Livewire\Attributes\On('updateOrder')]
    public function updateOrder($ids)
    {
        abort_if(!auth()->user()->can('menus.edit'), 403);

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
                            @foreach($data->children ?? [] as $item)
                                <tr data-id="{{ $item->id }}" wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{$counter}}
                                    </th>
                                    <td>

                                        {{$item->title}}
                                    </td>
                                    <td>
                                        {{$types[$item->type] ?? ''}}
                                    </td>
                                    <td>
                                        {{$item->parent->title?? ''}}
                                    </td>
                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                    class="ri-edit-line"></i></a>

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

                <form wire:submit="save">

                    <div class="modal-header">
                        <h6 class="modal-title">
                            {{$info['create_item'] ?? 'ایجاد آیتم منو'}}
                        </h6>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body text-start">

                        <div class="row">

                            {{-- عنوان --}}
                            <div class="col-xl-6">
                                <label class="form-label">
                                    عنوان
                                </label>

                                <input
                                    wire:model.lazy="title"
                                    type="text"
                                    class="form-control @error('title') is-invalid @enderror"
                                    placeholder="عنوان آیتم منو را وارد کنید">

                                @error('title')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- نوع --}}
                            <div class="col-xl-6">
                                <label class="form-label">
                                    نوع لینک
                                </label>

                                <select
                                    wire:model.lazy="type"
                                    class="form-select @error('type') is-invalid @enderror"
                                >
                                    <option value="">انتخاب کنید</option>
                                    <option value="page">صفحه</option>
                                    <option value="category">دسته‌بندی (تکی)</option>
                                    <option value="product">محصول (تکی)</option>
                                    <option value="article">مقاله (تکی)</option>
                                    <option value="external">لینک خارجی</option>
                                </select>

                                @error('type')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- لینک خارجی --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    لینک خارجی
                                </label>

                                <input
                                    wire:model.lazy="url"
                                    type="text"
                                    class="form-control @error('url') is-invalid @enderror"
                                    placeholder="https://...">

                                @error('url')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>


                            {{-- parent (زیرمنو) --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    آیتم والد
                                </label>

                                <select
                                    wire:model.lazy="parent_id"
                                    class="form-select @error('parent_id') is-invalid @enderror"
                                >
                                    <option value="">بدون والد (سطح اول)</option>

                                    @foreach($menuItems ?? [] as $item)
                                        <option value="{{ $item->id }}">
                                            {{ $item->title }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('parent_id')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- type_view (نوع نمایش) --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    نوع نمایش
                                </label>

                                <select
                                    wire:model.lazy="view_type"
                                    class="form-select @error('view_type') is-invalid @enderror"
                                >
                                    <option value="">نوع نمایش</option>
                                    <option value="mega_tabs">مگا منو تبی</option>
                                    <option value="mega_list">مگا لیست</option>
                                    <option value="dropdown">آبشاری</option>
                                </select>

                                @error('view_type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- reference_id --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    انتخاب محتوا
                                </label>

                                <select
                                    wire:model.lazy="reference_id"
                                    class="form-select @error('reference_id') is-invalid @enderror"
                                >
                                    <option value="">انتخاب کنید</option>

                                    @foreach($references as $item)
                                        <option value="{{ $item->id }}">
                                            {{ $item->title ?? $item->name }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('reference_id')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    برچسب
                                </label>

                                <select
                                    wire:model.lazy="badge"
                                    class="form-select @error('badge') is-invalid @enderror"
                                >
                                    <option value="">انتخاب کنید</option>
                                    <option value="normal">عادی</option>
                                    <option value="special">ویژه</option>
                                </select>

                                @error('badge')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- sort --}}
                            <div class="col-xl-6 mt-3">
                                <label class="form-label">
                                    ترتیب
                                </label>

                                <input
                                    wire:model.lazy="sort"
                                    type="number"
                                    class="form-control @error('sort') is-invalid @enderror"
                                    placeholder="0">

                                @error('sort')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <div wire:loading.remove wire:target="save">

                            <button type="submit" class="btn btn-primary">
                                ذخیره
                            </button>

                            <button type="button"
                                    class="btn btn-light"
                                    data-bs-dismiss="modal">
                                بستن
                            </button>

                        </div>

                        {{-- loading --}}
                        <div wire:loading wire:target="save" class="spinner-grow text-info">
                            <span class="visually-hidden">در حال بارگیری...</span>
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
    @push('scripts')
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
