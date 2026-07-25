<?php

use Livewire\Component;
use \App\Models\Page;
use \App\Models\PageSection;
new class extends Component
{

    public $info=[];
    public $selectItem;
    public $data;
    public $formData;
    public $section_id;
    public $sort=1;
    public $page;
    public $parentModel;
    public $sectionImage;
    public $media = [];
    public $name = '';
    public $location= '';


    public $search;
    public $ids;
    public PageSection $model;
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount($id,Page $model)
    {
        abort_if(!auth()->user()->can('sections.view'), 403);

        $this->model=new PageSection();
        $this->parentModel=$model;
        $this->ids=$id;
        $this->info['header']='لیست سکشن ها';
        $this->info['create']='افزودن سکشن';
        $this->info['delete']='حذف سکشن';
        $this->info['personal']='سکشن';
        $this->info['table']['headers']=[
            '#',
            'نام سکشن',
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
                text:'سکشن با موفقیت حذف شد.'
            );
        }

    }
    public function loadData()
    {
        $this->page=$this->parentModel->find($this->ids);
        $query = $this->model->where('page_id',$this->page?->id)->where(function ($query) {
            $query->where('title', 'LIKE', '%' . $this->search . '%');
        });

        $this->data = $query->orderBy('sort')->get();
    }


    public function get_data($id)
    {
        $this->selectItem = $this->model
            ->with('section')
            ->findOrFail($id);

        $dbData = $this->selectItem->data ?? [];

        $default = $this->defaultData(
            $this->selectItem->section->key
        );

        $this->formData = array_merge($default, $dbData);
    }
    protected function defaultData(string $key): array
    {

        return match ($key) {
            'hero' => [
                'title' => '',
                'subtitle' => '',
                'description' => '',

            ],
            'category-section' => [
                'tagline' => '',
                'title' => '',

            ],
            'courses' => [
                'title' => '',
            ],
            'about' => [
                'title' => '',
                'description' => '',
            ],
            'contact' => [
                'location' => '',
                'mobile' => '',
                'email' => '',
                'work_hours' => '',
            ],
            'contact-form' => [
                'title' => '',
                'image_1' => '',
            ],

            'faq' => [
                'title' => '',
            ],

            default => [],
        };
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('parentModel','ids','model','info','data');
        }else{
            $this->resetExcept(['parentModel','ids','model','info','data']);
            $this->dispatch('close-modal');

        }
    }

    protected function rules(): array
    {
        return match ($this->selectItem?->section->key) {

            'hero' => [
                'formData.title' => ['required', 'string', 'max:255'],
                'formData.subtitle' => ['required', 'string', 'max:255'],
                'formData.description' => ['required', 'string', 'max:5000'],
                'media.image_1' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'media.image_2' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ],
            'category-courses' => [
                'formData.tagline' => ['required', 'string', 'max:255'],
                'formData.title' => ['nullable', 'string', 'max:255'],
                'media.image_1' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            ],
            'about' => [
                'formData.title' => ['required', 'string', 'max:255'],
                'formData.description' => ['nullable', 'string', 'max:5000'],
                'media.image_1' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'media.image_2' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ],
            'courses' => [
                'formData.title' => ['required', 'string', 'max:255'],
            ],
            'contact' => [
                'formData.location' => ['required', 'string'],
                'formData.mobile' => ['required', 'string'],
                'formData.email' => ['required', 'string'],
                'formData.work_hours' => ['required', 'string'],
            ],
            'contact-form' => [
                'formData.title' => ['required', 'string'],
            ],
            'team' => [
                'formData.title' => ['required', 'string', 'max:255'],
            ],

            'faq' => [
                'formData.title' => ['required', 'string', 'max:255'],
            ],

            default => [
                'formData.title' => ['required'],
            ],
        };
    }


    public function save()
    {
        abort_if(!auth()->user()->can('sections.create'), 403);

        $data = $this->validate();

        $payload = [
            'data' => $data['formData'],
        ];
        if ($this->selectItem) {
            $item=tap($this->selectItem)->update($payload);
        } else {
            $item=$this->model::create($payload);
        }
        foreach ($this->media ?? [] as $collection => $file) {
            if (!$file) continue;
            $item->media()
                ->where('collection', $collection)
                ->delete();
            $this->upload(
                $file,
                $item,
                $collection
            );
        }
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
    public function addSection()
    {
        abort_if(!auth()->user()->can('sections.create'), 403);

        $data=$this->validate([
            'section_id'=> 'required',
            'sort'=> 'required|numeric',
        ]);
        $data['page_id'] = $this->ids;
        $section=\App\Models\Section::find($data['section_id']);
        $data['title'] = $section->name;
        $this->model->create($data);
        $this->resetData();
        $this->loadData();
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text:'سکشن با موفقیت ایجاد شد.'
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
                <button wire:click="resetData()" data-bs-effect="effect-flip-horizontal" data-bs-toggle="modal" href="#add_section" class="btn btn-success-light btn-wave me-0">
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
                            {{ $selectItem?->title ?? 'افزودن سکشن' }}
                        </h6>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                        </button>

                    </div>

                    <div class="modal-body text-start">

                        @if($selectItem)
                                @switch($selectItem?->section?->key)

                                    @case('hero')
                                        @include('dashboard.forms.hero')
                                        @break

                                    @case('blog')
                                        @include('dashboard.forms.blog')
                                        @break

                                    @case('team')
                                        @include('dashboard.forms.team')
                                        @break

                                    @case('category-courses')
                                        @include('dashboard.forms.category-courses')
                                        @break


                                    @case('courses')
                                        @include('dashboard.forms.courses')
                                        @break

                                    @case('about')
                                        @include('dashboard.forms.about')
                                        @break

                                    @case('contact')
                                        @include('dashboard.forms.contact')
                                        @break

                                    @case('contact-form')
                                        @include('dashboard.forms.contact-form')
                                        @break



                                    @default
                                        <div class="alert alert-warning">
                                            فرم این سکشن تعریف نشده است.
                                        </div>

                                @endswitch
                        @endif


                    </div>

                    <div class="modal-footer">

                        <div wire:loading.remove wire:target="save">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                ذخیره

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
                            class="spinner-grow text-info">

                        <span class="visually-hidden">
                            در حال ذخیره...
                        </span>

                        </div>

                    </div>

                </form>

            </div>
        </div>
    </div>
    <div wire:ignore.self class="modal fade" id="add_section">
        <div class="modal-dialog modal-dialog-centered modal-xl text-center" role="document">
            <div class="modal-content modal-content-demo">

                <form wire:submit="addSection">

                    <div class="modal-header">
                        <h6 class="modal-title">
                            {{$info['create'] ?? 'Hero Section'}}
                        </h6>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body text-start">

                        <div class="row">

                            {{-- TITLE --}}
                            <div class="col-xl-6 mb-3">
                                <label class="form-label">سکشن</label>

                               <select wire:model.lazy="section_id" class="form-select">
                                   <option value="">لطفا سکشن را انتخاب کنید</option>
                                   @foreach(\App\Models\Section::pluck('name','id') as $k => $s)
                                       <option value="{{$k}}">{{$s}}</option>
                                   @endforeach
                               </select>

                                @error('section_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- SUBTITLE --}}
                            <div class="col-xl-6 mb-3">
                                <label class="form-label">جایگاه</label>

                                <input
                                    wire:model.lazy="sort"
                                    type="number"
                                    class="form-control"
                                    placeholder="مثلا: 1">
                                @error('sort')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- DESCRIPTION --}}


                        </div>

                    </div>

                    <div class="modal-footer">

                        <div wire:loading.remove wire:target="addSection">

                            <button type="submit" class="btn btn-primary">
                                ذخیره
                            </button>

                            <button class="btn btn-light" data-bs-dismiss="modal" type="button">
                                بستن
                            </button>

                        </div>

                        {{-- loading --}}
                        <div wire:loading wire:target="addSection" class="spinner-grow text-info">
                            <span class="visually-hidden">در حال ذخیره...</span>
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
