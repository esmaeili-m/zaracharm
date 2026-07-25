<?php

use Livewire\Component;
use \App\Models\Tag;
use \App\Services\FileUploadService;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $title;
    public $description;
    public $slug;
    public $search;
    public Tag $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Tag $model)
    {
        abort_if(!auth()->user()->can('tags.view'), 403);

        $this->model=$model;
        $this->info['header']='لیست تگ ها';
        $this->info['create']='افزودن تگ';
        $this->info['delete']='حذف تگ';
        $this->info['personal']='تگ';
        $this->info['table']['headers']=[
            '#',
            'عنوان',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();
    }


    public function delete()
    {
        abort_if(!auth()->user()->can('tags.delete'), 403);

        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->loadData();
            $this->resetData('close');
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text:'آیتم  با موفقیت حذف شد.'
            );
        }


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
            text:'آیتم با موفقیت آپدیت شد.'
        );
    }
    public function loadData()
    {
        $query = $this->model
            ->where(function ($query) {
                $query->where('title', 'LIKE', '%' . $this->search . '%')->
                Orwhere('description', 'LIKE', '%' . $this->search . '%');
            });

        $this->data = $query->get();
    }

    public function updatedTitle()
    {
        $this->slug = Tag::generateSlugFrom($this->title);
    }
    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->title=$this->selectItem->title;
        $this->slug=$this->selectItem->slug;
        $this->description=$this->selectItem->description;
        $this->dispatch('editor-update');

    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
        }else{
            $this->resetExcept(['model','info','data']);
            $this->dispatch('close-modal');
        }
        $this->dispatch('editor-update');

    }
    public function rules()
    {
        $itemId = $this->selectItem?->id ?? null;

        return [

            'title' => [
                'required',
                'string',
                'max:200',
                'min:2',
                \Illuminate\Validation\Rule::unique('tags', 'title')->ignore($itemId),

            ],
            'slug' => [
                'required',
                'string',
                'max:200',
                'regex:/^[\p{Arabic}\p{L}\p{N}-]+$/u',
                \Illuminate\Validation\Rule::unique('tags', 'slug')->ignore($itemId),
            ],
            'description' => 'nullable'

        ];
    }

    public function messages()
    {
        return [
            // title
            'title.required' => 'وارد کردن عنوان تگ الزامی است.',
            'title.string' => 'عنوان تگ باید متن باشد.',
            'title.max' => 'عنوان تگ نمی‌تواند بیشتر از ۲۰۰ کاراکتر باشد.',
            'title.min' => 'عنوان تگ باید حداقل ۲ کاراکتر باشد.',
            'title.unique' => 'این عنوان قبلاً ثبت شده است. لطفاً از عنوان دیگری استفاده کنید.',

            // slug
            'slug.required' => 'وارد کردن اسلاگ الزامی است.',
            'slug.string' => 'اسلاگ باید متن باشد.',
            'slug.max' => 'اسلاگ نمی‌تواند بیشتر از ۲۰۰ کاراکتر باشد.',
            'slug.regex' => 'اسلاگ باید فقط شامل حروف کوچک انگلیسی، اعداد و خط تیره (-) باشد.',
            'slug.unique' => 'این اسلاگ قبلاً ثبت شده است. لطفاً از اسلاگ دیگری استفاده کنید.',


        ];
    }

    public function save(){
        abort_if(!auth()->user()->can('tags.create'), 403);

        $data= $this->validate();

        $tag = $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);
        $this->loadData();
        $this->resetData('close');
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $this->selectItem
                ? 'آیتم  با موفقیت ویرایش شد.'
                : 'آیتم  جدید با موفقیت ایجاد شد.'
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
                                        {{$item->title}}
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
        <div class="modal-dialog modal-dialog-centered text-center modal-lg" role="document">
            <div class="modal-content modal-content-demo">
                <form wire:submit.prevent="save()">
                    <div class="modal-header">
                        <h6 class="modal-title">{{$info['create']}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                        <div class="row">
                            <div class="col-xl-6">
                                <label  for="input-rounded" class="form-label ">عنوان {{$info['personal'] ?? ''}}</label>
                                <input wire:model.lazy="title" type="text" class="form-control @error('title') is-invalid @enderror" id="input-rounded" placeholder="لطفا عنوان {{$info['personal'] ?? ''}} را وارد کنید">
                                @error('title')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-6">
                                <label  for="input-rounded" class="form-label ">آدرس {{$info['personal'] ?? ''}}</label>
                                <input wire:model.lazy="slug" type="text" class="form-control @error('slug') is-invalid @enderror" id="input-rounded" placeholder="لطفا آدرس {{$info['personal'] ?? ''}} را وارد کنید">
                                @error('slug')
                                <div  class="invalid-feedback">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>
                            <div class="col-xl-12 mt-3">
                                <label  for="input-rounded" class="form-label">توضیحات {{$info['personal'] ?? ''}}</label>
                                <div wire:ignore>
                                    <div id="editor"></div>
                                </div>
                                @error('description')
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
@endpush
