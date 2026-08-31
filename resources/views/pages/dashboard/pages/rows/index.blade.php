<?php

use Livewire\Component;
use \App\Models\PageRow;
use \App\Models\Page;
new class extends Component
{

    public $info=[];
    public $selectItem;
    public $data;
    public ?string $title = null;

    public int $sort = 0;

    public int $gap = 4;

    public int $padding_top = 0;

    public int $padding_bottom = 0;

    public string $container = 'boxed';

    public $search;
    public PageRow $model;
    public Page $page;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Page $page,PageRow $model)
    {
        abort_if(!auth()->user()->can('sections.view'), 403);

        $this->model=$model;
        $this->page=$page;
        $this->info['header'] = 'لیست ردیف‌های صفحه';
        $this->info['create'] = 'افزودن ردیف';
        $this->info['delete'] = 'حذف ردیف';
        $this->info['personal'] = 'ردیف';
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
        $query = $this->model->where('page_id',$this->page->id)->where(function ($query) {
            $query->where('title', 'LIKE', '%' . $this->search . '%');
        });

        $this->data = $query->get();
    }


    public function get_data($id)
    {
        $this->selectItem = $this->model->findOrFail($id);

        $this->title = $this->selectItem->title;

        $this->sort = $this->selectItem->sort;

        $this->gap = $this->selectItem->gap;

        $this->padding_top = $this->selectItem->padding_top;

        $this->padding_bottom = $this->selectItem->padding_bottom;

        $this->container = $this->selectItem->container;
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data','page');
        }else{
            $this->resetExcept(['model','info','data','page']);
            $this->dispatch('close-modal');

        }
    }
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],

            'sort' => ['required', 'integer', 'min:0'],

            'gap' => ['required', 'integer', 'between:0,64'],

            'padding_top' => ['required', 'integer', 'between:0,64'],

            'padding_bottom' => ['required', 'integer', 'between:0,64'],

            'container' => ['required', 'in:boxed,full'],
        ];
    }
    protected function messages(): array
    {
        return [

            'title.required' => 'عنوان الزامی می باشد.',
            'title.string' => 'عنوان باید متن باشد.',
            'title.max' => 'عنوان نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',

            'sort.required' => 'ترتیب نمایش الزامی است.',
            'sort.integer' => 'ترتیب نمایش باید عدد باشد.',
            'sort.min' => 'ترتیب نمایش نمی‌تواند منفی باشد.',

            'gap.required' => 'فاصله بین سکشن‌ها الزامی است.',
            'gap.integer' => 'فاصله بین سکشن‌ها باید عدد باشد.',
            'gap.between' => 'فاصله بین سکشن‌ها نامعتبر است.',

            'padding_top.required' => 'فاصله بالا الزامی است.',
            'padding_top.integer' => 'فاصله بالا باید عدد باشد.',
            'padding_top.between' => 'فاصله بالا نامعتبر است.',

            'padding_bottom.required' => 'فاصله پایین الزامی است.',
            'padding_bottom.integer' => 'فاصله پایین باید عدد باشد.',
            'padding_bottom.between' => 'فاصله پایین نامعتبر است.',

            'container.required' => 'نوع Container را انتخاب کنید.',
            'container.in' => 'نوع Container نامعتبر است.',

        ];
    }
    public function save()
    {
        abort_if(!auth()->user()->can('sections.create'), 403);

        $data = $this->validate();
        $data['page_id'] = $this->page->id;
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

                                        {{$item->title}}
                                    </td>

                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            @can('sections.view')
                                                <a title="افزودن ایتم" href="{{route('pages.rows.sections',$item->id)}}" class="text-warning fs-14 lh-1"><i
                                                        class="ri-list-view"></i></a>
                                            @endcan
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
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content modal-content-demo">

                <form wire:submit="save">

                    <div class="modal-header">
                        <h6 class="modal-title">
                            {{ $info['create'] }}
                        </h6>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body text-start">

                        <div class="row g-3">

                            {{-- عنوان --}}
                            <div class="col-md-6">
                                <label class="form-label">
                                    عنوان ردیف
                                </label>

                                <input
                                    wire:model.lazy="title"
                                    type="text"
                                    class="form-control @error('title') is-invalid @enderror"
                                    placeholder="مثلاً: هیرو">

                                @error('title')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- ترتیب --}}
                            <div class="col-md-6">
                                <label class="form-label">
                                    ترتیب نمایش
                                </label>

                                <input
                                    wire:model.lazy="sort"
                                    type="number"
                                    min="0"
                                    class="form-control @error('sort') is-invalid @enderror">

                                @error('sort')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- Container --}}
                            <div class="col-md-6">
                                <label class="form-label">
                                    نوع Container
                                </label>

                                <select
                                    wire:model="container"
                                    class="form-select @error('container') is-invalid @enderror">

                                    <option value="boxed">
                                        Boxed
                                    </option>

                                    <option value="full">
                                        Full Width
                                    </option>

                                </select>

                                @error('container')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- Gap --}}
                            <div class="col-md-6">
                                <label class="form-label">
                                    فاصله بین سکشن‌ها (Gap)
                                </label>

                                <input
                                    wire:model.lazy="gap"
                                    type="number"
                                    min="0"
                                    class="form-control @error('gap') is-invalid @enderror">

                                @error('gap')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- Padding Top --}}
                            <div class="col-md-6">
                                <label class="form-label">
                                    فاصله از بالا (Padding Top)
                                </label>

                                <input
                                    wire:model.lazy="padding_top"
                                    type="number"
                                    min="0"
                                    class="form-control @error('padding_top') is-invalid @enderror">

                                @error('padding_top')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- Padding Bottom --}}
                            <div class="col-md-6">
                                <label class="form-label">
                                    فاصله از پایین (Padding Bottom)
                                </label>

                                <input
                                    wire:model.lazy="padding_bottom"
                                    type="number"
                                    min="0"
                                    class="form-control @error('padding_bottom') is-invalid @enderror">

                                @error('padding_bottom')
                                <div class="invalid-feedback">
                                    {{ $message }}
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
                                type="button"
                                class="btn btn-light"
                                data-bs-dismiss="modal">
                                بستن
                            </button>

                        </div>

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
