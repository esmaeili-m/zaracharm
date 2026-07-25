<?php

use Livewire\Component;
use \App\Models\Section;
new class extends Component
{

    public $info=[];
    public $selectItem;
    public $data;
    public $component;
    public $key;
    public $has_items;
    public $name = '';
    public $location= '';


    public $search;
    public Section $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Section $model)
    {
        abort_if(!auth()->user()->can('sections.view'), 403);

        $this->model=$model;
        $this->info['header']='لیست سکشن ها';
        $this->info['create']='افزودن سکشن';
        $this->info['delete']='حذف سکشن';
        $this->info['personal']='سکشن';
        $this->info['table']['headers']=[
            '#',
            'نام',
            'عملیات',
        ];
        $this->loadData();
    }


    public function delete()
    {
        abort_if(!auth()->user()->can('sections.delete'), 403);

        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->loadData();
            $this->resetData('close');
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: 'سکشن با موفقیت حذف شد.'
            );
        }

    }
    public function loadData()
    {
        $query = $this->model->where(function ($query) {
            $query->where('name', 'LIKE', '%' . $this->search . '%');
        });

        $this->data = $query->get();
    }


    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->name=$this->selectItem->name;
        $this->key=$this->selectItem->key;
        $this->component=$this->selectItem->component;
        $this->has_items=$this->selectItem->has_items;
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

    protected function rules(): array
    {
        $selectItem=$this->selectItem?->id;
        return [
            'name' => ['required', 'string', 'max:255'],

            'key' => [
                'required',
                'string',
                'max:255',
                'unique:sections,key,'.$selectItem,
                'regex:/^[a-z0-9_-]+$/'
            ],

            'component' => ['required', 'string', 'max:255'],

            'has_items' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'نام سکشن الزامی است.',
            'name.string' => 'نام سکشن باید متنی باشد.',
            'name.max' => 'نام سکشن نباید بیشتر از ۲۵۵ کاراکتر باشد.',

            'key.required' => 'کلید سکشن الزامی است.',
            'key.string' => 'کلید سکشن باید متنی باشد.',
            'key.max' => 'کلید سکشن نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'key.unique' => 'این کلید قبلاً ثبت شده است.',
            'key.regex' => 'کلید سکشن فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.',

            'component.required' => 'کامپوننت نمایش الزامی است.',
            'component.string' => 'کامپوننت نمایش باید متنی باشد.',
            'component.max' => 'کامپوننت نمایش نباید بیشتر از ۲۵۵ کاراکتر باشد.',

            'has_items.boolean' => 'وضعیت آیتم‌ها نامعتبر است.',
        ];
    }
    public function save()
    {
        abort_if(!auth()->user()->can('sections.create'), 403);

        $data = $this->validate();
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
                ? 'سکشن با موفقیت ویرایش شد.'
                : 'سکشن جدید با موفقیت ایجاد شد.'
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
            @can('sections.create')

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
                            <tbody>
                            @php($counter=1)
                            @foreach($data ?? [] as $item)
                                <tr wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{$counter}}
                                    </th>
                                    <td>

                                        {{$item->name}}
                                    </td>

                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            @can('sections.edit')

                                                <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                        class="ri-edit-line"></i></a>
                                            @endcan
                                                @can('sections.delete')

                                            <a  data-bs-toggle="modal" href="#delete" wire:click="get_data({{$item->id}})"  class="text-danger fs-14 lh-1"><i
                                                    class="ri-delete-bin-5-line"></i></a>
                                                @endcan
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
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content modal-content-demo">

                <form wire:submit="save">

                    <div class="modal-header">
                        <h6 class="modal-title">
                            {{$info['create']}}
                        </h6>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body text-start">

                        <div class="row g-3">

                            {{-- نام سکشن --}}
                            <div class="col-xl-6">
                                <label class="form-label">
                                    نام سکشن
                                </label>

                                <input
                                    wire:model.lazy="name"
                                    type="text"
                                    class="form-control @error('name') is-invalid @enderror"
                                    placeholder="مثلاً: سوالات متداول">

                                @error('name')
                                <div class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- کلید سکشن --}}
                            <div class="col-xl-6">
                                <label class="form-label">
                                    کلید سکشن
                                </label>

                                <input
                                    wire:model.lazy="key"
                                    type="text"
                                    dir="ltr"
                                    class="form-control @error('key') is-invalid @enderror"
                                    placeholder="faq-section">

                                @error('key')
                                <div class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- کامپوننت --}}
                            <div class="col-xl-6">
                                <label class="form-label">
                                    کامپوننت نمایش
                                </label>

                                <input
                                    wire:model.lazy="component"
                                    type="text"
                                    dir="ltr"
                                    class="form-control @error('component') is-invalid @enderror"
                                    placeholder="sections.faq">

                                @error('component')
                                <div class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                            {{-- دارای آیتم --}}
                            <div class="col-xl-6 d-flex align-items-center">

                                <div class="form-check mt-4">

                                    <input
                                        wire:model="has_items"
                                        class="form-check-input"
                                        type="checkbox"
                                        id="hasItems">

                                    <label
                                        class="form-check-label"
                                        for="hasItems">

                                        این سکشن دارای آیتم است

                                    </label>

                                </div>

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
                                type="button"
                                class="btn btn-light"
                                data-bs-dismiss="modal">
                                بستن
                            </button>

                        </div>

                        {{-- loading --}}
                        <div
                            wire:loading
                            wire:target="save"
                            class="spinner-grow text-info"
                            role="status">

                        <span class="visually-hidden">
                            در حال بارگذاری...
                        </span>

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

</div>
