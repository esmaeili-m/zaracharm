<?php

use Livewire\Component;
use \App\Models\Gallery;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $files = [];
    public $info=[];
    public $selectItem;
    public $data;
    public $name;
    public $search;
    public Gallery $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Gallery $model)
    {
        abort_if(!auth()->user()->can('galleries.view'), 403);

        $this->model=$model;
        $this->info['header']='لیست گالری';
        $this->info['create']='افزودن دسته گالری';
        $this->info['delete']='حذف گالری';
        $this->info['personal']='گالری';
        $this->info['table']['headers']=[
            '#',
            'نام',
            'عملیات',
        ];
        $this->loadData();
    }

    public function delete()
    {
        abort_if(!auth()->user()->can('galleries.delete'), 403);

        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->loadData();
            $this->resetData('close');
        }

    }
    public function loadData()
    {
        $query = $this->model
            ->where(function ($query) {

                $query->where('name', 'LIKE', '%' . $this->search . '%');
            });

        $this->data = $query->get();
    }
    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->name=$this->selectItem->name;
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
        }else{
            $this->resetExcept(['selectItem','model','info','data']);
            $this->dispatch('close-modal');

        }
    }

    public function rules()
    {
        $categoryId = $this->selectItem?->id ?? null;

        return [

            'name' => [
                'required',
                'string',
                'max:200',
                'min:2',
            ],
        ];
    }

    public function messages()
    {
        return [
            // parent_id

            // name
            'name.required' => 'وارد کردن نام دسته‌بندی الزامی است.',
            'name.string' => 'نام دسته‌بندی باید متن باشد.',
            'name.max' => 'نام دسته‌بندی نمی‌تواند بیشتر از ۲۰۰ کاراکتر باشد.',
            'name.min' => 'نام دسته‌بندی باید حداقل ۲ کاراکتر باشد.',
        ];
    }

    public function save(){
        abort_if(!auth()->user()->can('galleries.create'), 403);

        $data= $this->validate();
        $item = $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);


        // رفرش دیتا
        $this->loadData();

        // ریست فرم
        $this->resetData('close');
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $this->selectItem
                ? 'گالری با موفقیت ویرایش شد.'
                : 'گالری جدید با موفقیت ایجاد شد.'
        );
    }
    public function deleteMedia($id)
    {
        abort_if(!auth()->user()->can('galleries.delete'), 403);

        $this->selectItem->media()->findOrFail($id)->delete();
    }


    public function save_attachments()
    {
        abort_if(!auth()->user()->can('galleries.create'), 403);

        $this->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,webm',
                'max:51200',
            ],
        ]);

        foreach ($this->files as $file) {
            $this->upload(
                $file,
                $this->selectItem,
                'gallery'
            );
        }
        $this->selectItem->fresh();
        $this->reset('files');

        $this->dispatch('success', 'فایل‌ها با موفقیت آپلود شدند');
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
            @can('galleries.create')

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
                                            @can('galleries.create')

                                            <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                    class="ri-edit-line"></i></a>
                                            @endcan
                                                @can('galleries.edit')

                                            <a data-bs-toggle="modal" href="#attachments" wire:click="get_data({{$item->id}})" class="text-info fs-14 lh-1"><i
                                                    class="ri-list-radio"></i></a>
                                                @endcan
                                                    @can('galleries.delete')

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
        <div class="modal-dialog modal-dialog-centered text-center modal-md" role="document">
            <div class="modal-content modal-content-demo">
                <form wire:submit.prevent="save()">
                    <div class="modal-header">
                        <h6 class="modal-title">{{$info['create']}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                        <div class="row">
                            <div class="col-xl-12">
                                <label  for="input-rounded" class="form-label ">نام {{$info['personal'] ?? ''}}</label>
                                <input wire:model.lazy="name" type="text" class="form-control @error('name') is-invalid @enderror" id="input-rounded" placeholder="لطفا نام {{$info['personal'] ?? ''}} را وارد کنید">
                                @error('name')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <div wire:loading.remove wire:target="save">
                            <button class="btn btn-primary" type="submit">
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

                </form>

            </div>
        </div>
    </div>
    <div wire:ignore.self class="modal fade" id="attachments">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">

                <form wire:submit.prevent="save_attachments">

                    {{-- HEADER --}}
                    <div class="modal-header">
                        <h5 class="modal-title">مدیریت فایل‌های گالری</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    {{-- BODY --}}
                    <div class="modal-body">

                        <div class="row">

                            {{-- ===== UPLOAD SECTION ===== --}}
                            <div class="col-lg-4">

                                <label class="form-label">انتخاب فایل‌ها</label>



                                <div x-data="{ progress: 0 }"
                                     x-on:livewire-upload-start="progress = 0"
                                     x-on:livewire-upload-finish="progress = 100"
                                     x-on:livewire-upload-error="progress = 0"
                                     x-on:livewire-upload-progress="progress = $event.detail.progress">

                                    <input wire:model="files" type="file" multiple class="form-control mb-1">
                                    <div class="progress mt-2" x-show="progress > 0">
                                        <div class="progress-bar"
                                             role="progressbar"
                                             :style="'width: ' + progress + '%'">
                                            <span x-text="progress + '%'"></span>
                                        </div>
                                    </div>
                                </div>
                                {{-- Progress Bar --}}
                                @error('files.*')
                                <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                                <button class="btn btn-primary w-100 mt-3" type="submit">
                                    آپلود فایل‌ها
                                </button>

                            </div>
                            {{-- ===== LIST SECTION ===== --}}
                            <div class="col-lg-8">

                                <div class="table-responsive">

                                    <table class="table table-bordered align-middle">

                                        <thead>
                                        <tr>
                                            <th>پیش‌نمایش</th>
                                            <th>نوع</th>
                                            <th>عملیات</th>
                                        </tr>
                                        </thead>

                                        <tbody>

                                        @forelse($this->selectItem?->media()->get() ?? [] as $media)

                                            @php($isImage = str_starts_with($media->mime_type, 'image/'))

                                            <tr>

                                                {{-- preview --}}
                                                <td>
                                                    @if($isImage)
                                                        <img src="{{ url('/media/'.$media->file_path) }}"
                                                             style="width:60px;height:60px;object-fit:cover"
                                                             class="rounded">
                                                    @else
                                                        <i class="ri-video-line fs-2"></i>
                                                    @endif
                                                </td>
                                                {{-- type --}}
                                                <td>
                                                    @if($isImage)
                                                        <span class="badge bg-success">تصویر</span>
                                                    @else
                                                        <span class="badge bg-primary">ویدیو</span>
                                                    @endif
                                                </td>

                                                {{-- actions --}}
                                                <td>
                                                    <button
                                                        type="button"
                                                        wire:click="deleteMedia({{ $media->id }})"
                                                        class="btn btn-sm btn-danger">
                                                        حذف
                                                    </button>
                                                </td>

                                            </tr>

                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center">
                                                    هیچ فایلی وجود ندارد
                                                </td>
                                            </tr>
                                        @endforelse

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                        </div>

                    </div>

                    {{-- FOOTER --}}
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            بستن
                        </button>
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
