<?php

use Livewire\Component;
use \App\Models\Faq;
use \App\Models\Menu;
new class extends Component
{

    public $info=[];
    public $selectItem;
    public $data;
    public $name = '';
    public $location= '';


    public $search;
    public $convertLocation;
    public Menu $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Menu $model)
    {
        abort_if(!auth()->user()->can('menus.view'), 403);

        $this->model=$model;
        $this->info['header']='لیست منوها';
        $this->info['create']='افزودن منو';
        $this->info['delete']='حذف منو';
        $this->info['personal']='منو';
        $this->convertLocation = [
            'header' => 'منوی اصلی'
        ];
        $this->info['table']['headers']=[
            '#',
            'نام',
            'محل منو',
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
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text:'منو با موفقیت حذف شد.'
            );
            $this->resetData('close');
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
        $this->location=$this->selectItem->location;
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('convertLocation','model','info','data');
        }else{
            $this->resetExcept(['convertLocation','model','info','data']);
            $this->dispatch('close-modal');

        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'in:header,footer,mobile,sidebar'],
        ];
    }


    public function messages(): array
    {
        return [
            'name.required' => 'نام منو الزامی است.',
            'name.string' => 'نام منو باید متنی باشد.',
            'name.max' => 'نام منو نباید بیشتر از ۲۵۵ کاراکتر باشد.',

            'location.required' => 'محل نمایش منو الزامی است.',
            'location.in' => 'محل نمایش منو نامعتبر است (header, footer, mobile, sidebar).',
        ];
    }
    public function save()
    {
        abort_if(!auth()->user()->can('menus.create'), 403);

        $data = $this->validate();
        $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);

        $this->loadData();

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $this->selectItem
                ? 'منو با موفقیت ویرایش شد.'
                : 'منو جدید با موفقیت ایجاد شد.'
        );
        $this->resetData('close');

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
                                        {{$convertLocation[$item->location] ?? $item->location}}
                                    </td>
                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                    class="ri-edit-line"></i></a>

                                            <a  href="{{route('menus.item',$item->id)}}" class="text-warning fs-14 lh-1"><i
                                                    class="ri-list-view"></i></a>

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

                <form wire:submit="save()">

                    <div class="modal-header">
                        <h6 class="modal-title">
                            {{$info['create']}}
                        </h6>

                        <button aria-label="Close"
                                class="btn-close"
                                data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body text-start">

                        <div class="row">

                            <div class="col-xl-6">
                                <label class="form-label">
                                     {{$info['personal'] ?? ''}}
                                </label>

                                <input
                                    wire:model.lazy="name"
                                    type="text"
                                    class="form-control @error('name') is-invalid @enderror"
                                    placeholder="لطفا عنوان {{$info['personal'] ?? ''}} را وارد کنید">

                                @error('name')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-6">
                                <label class="form-label">
                                    {{$info['personal'] ?? ''}}
                                </label>

                                <select
                                    wire:model.lazy="location"
                                    class="form-select @error('location') is-invalid @enderror"
                                >
                                    <option value="">انتخاب کنید</option>

                                    <option value="header">هدر</option>
                                    <option value="footer">فوتر</option>
                                    <option value="mobile">موبایل</option>
                                    <option value="sidebar">سایدبار</option>
                                </select>

                                @error('location')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
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
                                class="btn btn-light"
                                data-bs-dismiss="modal"
                                type="button">
                                بستن
                            </button>

                        </div>

                        {{-- لودینگ --}}
                        <div wire:loading
                             wire:target="save"
                             class="spinner-grow text-info"
                             role="status">

                        <span class="visually-hidden">
                            در حال بارگیری...
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
