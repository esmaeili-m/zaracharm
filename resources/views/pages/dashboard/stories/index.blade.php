<?php

use Livewire\Component;
use \App\Models\Story;
use Illuminate\Validation\Rule;

new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $title='';
    public $type;
    public $user;
    public $avatar;
    public $url;
    public $duration=7000;
    public $link;
    public $featured_image;
    public $banner_image;
    public $editorImage;
    public $search;
    public Story $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Story $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->model=$model;
        $this->info['header']='لیست استوری ها';
        $this->info['create']='افزودن استوری';
        $this->info['delete']='حذف استوری';
        $this->info['personal']='استوری';
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
    public function loadData()
    {
        $query = $this->model->where(function ($query) {
            $query->where('user', 'LIKE', '%' . $this->search . '%');
        });

        $this->data = $query->orderBy('sort')->get();
    }
    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->title=$this->selectItem->title;
        $this->type=$this->selectItem->type;
        $this->user=$this->selectItem->user;
        $this->duration=$this->selectItem->duration;
        $this->link=$this->selectItem->link;
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
        }else{
            $this->resetExcept(['selectItem','model','info','data']);
            $this->dispatch('close-modal');
        }
        $this->dispatch('editor-update');

    }
    protected function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                'in:image,video',
            ],

            'user' => [
                'required',
                'string',
                'max:100',
            ],

            'avatar' => [
                $this->selectItem ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'url' => [
                $this->selectItem ? 'nullable' : 'required',
                'file',
                'mimes:jpg,jpeg,png,webp,mp4,webm,mov',
                'max:20480',
            ],

            'duration' => [
                'required',
                'integer',
                'min:1000',
                'max:60000',
            ],

            'link' => [
                'nullable',
                'url',
                'max:500',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'type.required' => 'انتخاب نوع استوری الزامی است.',
            'type.in' => 'نوع استوری انتخاب شده معتبر نیست.',

            'user.required' => 'وارد کردن نام استوری الزامی است.',
            'user.max' => 'نام استوری نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.',

            'avatar.max' => 'آدرس تصویر آواتار نمی‌تواند بیشتر از ۵۰۰ کاراکتر باشد.',

            'url.required' => 'وارد کردن آدرس استوری الزامی است.',
            'url.max' => 'آدرس استوری نمی‌تواند بیشتر از ۵۰۰ کاراکتر باشد.',

            'duration.required' => 'وارد کردن مدت زمان نمایش الزامی است.',
            'duration.integer' => 'مدت زمان نمایش باید عدد باشد.',
            'duration.min' => 'مدت زمان نمایش حداقل باید ۱ ثانیه باشد.',
            'duration.max' => 'مدت زمان نمایش حداکثر ۶۰ ثانیه است.',

            'link.url' => 'لینک وارد شده معتبر نیست.',
            'link.max' => 'لینک نمی‌تواند بیشتر از ۵۰۰ کاراکتر باشد.',
        ];
    }

    public function save(){
        abort_if(!auth()->user()->can('categories.create'), 403);
        $data= $this->validate();
        unset($data['avatar']);
        unset($data['url']);
        $item = $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);
        if ($this->avatar) {

            $item->media()
                ->where('collection', 'avatar')
                ->delete();

            $this->upload(
                $this->avatar,
                $item,
                'avatar'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | تصویر بنر
        |--------------------------------------------------------------------------
        */

        if ($this->url) {

            $item->media()
                ->where('collection', 'url')
                ->delete();

            $this->upload(
                $this->url,
                $item,
                'url'
            );
        }
        // رفرش دیتا
        $this->loadData();

        // ریست فرم
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
            @can('categories.view')

                <a href="{{route('stories.trash')}}" class="btn btn-warning-light btn-wave me-2">
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
                                        {{$item->user}}
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
        <div class="modal-dialog modal-dialog-centered text-center modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{$info['create']}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="save" id="save">

                        <div class="row">

                            {{-- نوع استوری --}}
                            <div class="col-xl-4">

                                <label class="form-label">
                                    نوع استوری
                                </label>

                                <select
                                    wire:model.lazy="type"
                                    class="form-select @error('type') is-invalid @enderror">

                                    <option value="">
                                        انتخاب کنید
                                    </option>

                                    <option value="image">
                                        تصویر
                                    </option>

                                    <option value="video">
                                        ویدیو
                                    </option>

                                </select>

                                @error('type')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            {{-- عنوان / نام استوری --}}
                            <div class="col-xl-4">

                                <label class="form-label">
                                    عنوان استوری
                                </label>

                                <input
                                    wire:model.lazy="user"
                                    type="text"
                                    class="form-control @error('user') is-invalid @enderror"
                                    placeholder="لطفا عنوان استوری را وارد کنید">

                                @error('user')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            {{-- مدت نمایش --}}
                            <div class="col-xl-4">

                                <label class="form-label">
                                    مدت نمایش
                                </label>

                                <select
                                    wire:model.lazy="duration"
                                    class="form-select @error('duration') is-invalid @enderror">

                                    <option value="3000">
                                        ۳ ثانیه
                                    </option>

                                    <option value="5000">
                                        ۵ ثانیه
                                    </option>

                                    <option value="7000">
                                        ۷ ثانیه
                                    </option>

                                    <option value="10000">
                                        ۱۰ ثانیه
                                    </option>

                                    <option value="15000">
                                        ۱۵ ثانیه
                                    </option>

                                </select>

                                @error('duration')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            {{-- تصویر آواتار --}}
                            <div class="col-xl-6 mt-3">

                                <label class="form-label">
                                    تصویر آواتار
                                </label>

                                <div
                                    x-data="{ progress: 0 }"
                                    x-on:livewire-upload-start="progress = 0"
                                    x-on:livewire-upload-finish="progress = 100"
                                    x-on:livewire-upload-error="progress = 0"
                                    x-on:livewire-upload-progress="progress = $event.detail.progress">

                                    <input
                                        wire:model.lazy="avatar"
                                        class="form-control mb-1 @error('avatar') is-invalid @enderror"
                                        type="file">

                                    <div
                                        class="progress mt-2"
                                        x-show="progress > 0">

                                        <div
                                            class="progress-bar"
                                            role="progressbar"
                                            :style="'width: ' + progress + '%'">

                                            <span x-text="progress + '%'"></span>

                                        </div>

                                    </div>

                                </div>

                                @error('avatar')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            {{-- تصویر / ویدیوی استوری --}}
                            <div class="col-xl-6 mt-3">

                                <label class="form-label">
                                    فایل استوری
                                </label>

                                <div
                                    x-data="{ progress: 0 }"
                                    x-on:livewire-upload-start="progress = 0"
                                    x-on:livewire-upload-finish="progress = 100"
                                    x-on:livewire-upload-error="progress = 0"
                                    x-on:livewire-upload-progress="progress = $event.detail.progress">

                                    <input
                                        wire:model.lazy="url"
                                        class="form-control mb-1 @error('url') is-invalid @enderror"
                                        type="file">

                                    <div
                                        class="progress mt-2"
                                        x-show="progress > 0">

                                        <div
                                            class="progress-bar"
                                            role="progressbar"
                                            :style="'width: ' + progress + '%'">

                                            <span x-text="progress + '%'"></span>

                                        </div>

                                    </div>

                                </div>

                                @error('url')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            {{-- لینک --}}
                            <div class="col-xl-8 mt-3">

                                <label class="form-label">
                                    لینک
                                </label>

                                <input
                                    wire:model.lazy="link"
                                    type="url"
                                    class="form-control @error('link') is-invalid @enderror"
                                    placeholder="https://example.com">

                                @error('link')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror

                            </div>


                            {{-- وضعیت --}}
                            <div class="col-xl-4 mt-3">

                                <label class="form-label">
                                    وضعیت
                                </label>

                                <select
                                    wire:model.lazy="status"
                                    class="form-select @error('status') is-invalid @enderror">

                                    <option value="1">
                                        فعال
                                    </option>

                                    <option value="0">
                                        غیرفعال
                                    </option>

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
