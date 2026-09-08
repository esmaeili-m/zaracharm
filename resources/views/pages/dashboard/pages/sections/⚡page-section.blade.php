<?php

use Livewire\Component;
use \App\Models\RowSection;
use \App\Models\PageRow;
use Illuminate\Http\UploadedFile;
new class extends Component {

    public $info = [];
    public $selectItem;
    public $data;
    public $formData;
    public $section_id;
    public $sort = 1;
    public $title;
    public $products;
    public $page;
    public $parentModel;
    public $sectionImage;
    public $media = [];
    public $images = [];
    public $sliders;
    public $campaigns;
    public $stories;
    public $articles;
    public $name = '';
    public $location = '';
    public array $layout = [
        'grid' => [
            'default' => 12,
            'md' => 12,
            'lg' => 12,
        ],

        'spacing' => [
            'padding_top' => 0,
            'padding_bottom' => 0,
        ],

        'container' => 'boxed',

        'visibility' => [
            'mobile' => true,
            'desktop' => true,
        ],
    ];

    public $search;
    public $ids;
    public $categories;
    public RowSection $model;
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(PageRow $row, RowSection $model)
    {
        abort_if(!auth()->user()->can('sections.view'), 403);

        $this->model = $model;
        $this->parentModel = $row;
        $this->ids = $row->id;
        $this->info['header'] = 'لیست سکشن ها';
        $this->info['create'] = 'افزودن سکشن';
        $this->info['delete'] = 'حذف سکشن';
        $this->info['personal'] = 'سکشن';
        $this->info['table']['headers'] = [
            '#',
            'نام سکشن',
            'عملیات',
        ];
        $this->loadData();
    }


    public function delete()
    {
        abort_if(!auth()->user()->can('sections.delete'), 403);

        if ($this->selectItem) {
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

    public function loadForm()
    {
        match ($this->selectItem?->section?->key) {

            'sliders' => $this->loadSliders(),
            'categories' => $this->loadCategories(),
            'products' => $this->loadProductsData(),
            'campaigns' => $this->loadCampaignsData(),
            'articles' => $this->loadArticlesData(),
            'stories' => $this->loadStoriesData(),
            default => null,

        };
    }


    private function loadSliders()
    {
        $this->sliders = \App\Models\Slider::query()
            ->where('status', true)->get();

    }
    private function loadArticlesData()
    {
        $this->sliders = \App\Models\Article::active()
           ->get();
    }    private function loadStoriesData()
    {
        $this->stories = \App\Models\Story::active()
           ->get();
    }

    private function loadCategories()
    {
        $this->categories = \App\Models\Category::query()
            ->where('status', true)->get();

    }

    private function loadProductsData()
    {
        $this->products = \App\Models\Product::query()
            ->active()->get();

    }

    private function loadCampaignsData()
    {
        $this->campaigns = \App\Models\Campaign::query()
            ->active()->get();

    }

    public function loadData()
    {
        $this->page = $this->parentModel->find($this->ids);
        $query = $this->model->where('page_row_id', $this->page?->id)->where(function ($query) {
            $query->where('title', 'LIKE', '%' . $this->search . '%');
        });

        $this->data = $query->orderBy('sort')->get();
    }


    public function get_data($id)
    {
        $this->selectItem = $this->model
            ->with('section')
            ->findOrFail($id);

        // عنوان عمومی سکشن
        $this->title = $this->selectItem->data['title'] ?? $this->selectItem->title;


        // تنظیمات layout
        $this->layout = $this->selectItem->layout ?? $this->defaultLayout();


        // تنظیمات اختصاصی سکشن
        $dbData = $this->selectItem->data ?? [];
        if($this->selectItem->media){
            foreach ($this->selectItem->media as $index => $image){
                $dbData['images']['image_'.($index + 1)]=$image->file_path;
            }
        }
        $default = $this->defaultData(
            $this->selectItem->section->key
        );
        $this->formData = array_merge($default, $dbData);
        $this->loadForm();
    }

    protected function defaultLayout(): array
    {
        return [

            'grid' => [
                'default' => 12,
                'md' => 12,
                'lg' => 12,
            ],

            'spacing' => [
                'padding_top' => 0,
                'padding_bottom' => 0,
            ],

            'container' => 'boxed',

            'visibility' => [
                'mobile' => true,
                'desktop' => true,
            ],

        ];
    }

    protected function defaultData(string $key): array
    {
        return match ($key) {


            'sliders' => [

                'slider_id' => null,

                'autoplay' => true,

                'loop' => true,

                'speed' => 5000,

            ],
            'map' => [

                'lat' =>  34.64216555482277,

                'long' => 50.85996057131541,


            ],
            'categories' => [
                'mode' => 'sales',
                'pictureMode' => 'background',
                'limit' => 8,
                'view' => 1,

                'category_ids' => [],
            ],

            'instantOffers' => [
                'mode' => 'random',
                'limit' => 8,
                'view' => 1,

            ],

            'products' => [
                'mode' => 'latest',
                'limit' => 8,
                'view' => 1,
                'pictureMode' => 'background',
                'product_ids' => [],
            ],
            'stories' => [
                'mode' => 'latest',
                'limit' => 8,
                'story_ids' => [],
            ],

            'articles' => [
                'mode' => 'latest',
                'limit' => 8,
                'view' => 1,
                'article_ids' => [],
            ],

            'campaigns' => [
                'campaign_id' => null,
                'limit' => 8,
                'view' => 1,
                'show_title' => true,
                'show_description' => true,
                'show_image' => true,
                'show_timer' => true,
            ],

            'about' => [
                'description' => null,
                'images' => [],
                'client' => '+1',
                'shop' => '+1',
                'service' => '98%',
                'support' => '24/7',
            ],

            'contact' => [
                'mode'=> 1
            ],

            'banner' => [

                'banner_id' => null,

            ],


            default => [],

        };
    }

    public function resetData($action = 'create')
    {
        if ($action == 'create') {
            $this->resetExcept('parentModel', 'ids', 'model', 'info', 'data', 'parentModel');
        } else {
            $this->resetExcept(['parentModel', 'ids', 'model', 'info', 'data', 'parentModel']);
            $this->dispatch('close-modal');

        }
    }

    protected function rules(): array
    {
        return array_merge(
            [

                // Layout عمومی
                'title' => [
                    'nullable',
                    'string',
                    'max:255'
                ],

                'layout.grid.default' => [
                    'required',
                    'integer',
                    'between:1,12'
                ],

                'layout.grid.md' => [
                    'required',
                    'integer',
                    'between:1,12'
                ],

                'layout.grid.lg' => [
                    'required',
                    'integer',
                    'between:1,12'
                ],

                'layout.spacing.padding_top' => [
                    'required',
                    'integer',
                    'min:0'
                ],

                'layout.spacing.padding_bottom' => [
                    'required',
                    'integer',
                    'min:0'
                ],

                'layout.container' => [
                    'required',
                    'in:boxed,full'
                ],

            ],

            match ($this->selectItem?->section->key) {


                'sliders' => [

                    'formData.slider_id' => [
                        'required',
                        'exists:sliders,id'
                    ],

                ],
                'categories' => [

                    'formData.mode' => [
                        'required',
                        'in:latest,sales,views,random,manual,all'
                    ],

                    'formData.limit' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],
                    'formData.view' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],

                    'formData.category_ids' => [
                        'required_if:formData.mode,manual',
                        'array'
                    ],

                    'formData.category_ids.*' => [
                        'exists:categories,id'
                    ],
                ],
                'instantOffers' => [

                    'formData.view' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],

                    'formData.mode' => [
                        'required',
                        'in:random,latest,sales,views,manual'
                    ],

                    'formData.limit' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],
                ],
                'products' => [

                    'formData.mode' => [
                        'required',
                        'in:latest,sales,views,random,manual'
                    ],

                    'formData.limit' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],

                    'formData.view' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],
                    'formData.pictureMode' => [
                        'nullable',
                    ],

                    'formData.product_ids' => [
                        'required_if:formData.mode,manual',
                        'array'
                    ],

                    'formData.product_ids.*' => [
                        'exists:products,id'
                    ],
                ],
                'stories' => [

                    'formData.mode' => [
                        'required',
                        'in:latest,random,manual'
                    ],

                    'formData.limit' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],



                    'formData.story_ids' => [
                        'required_if:formData.mode,manual',
                        'array'
                    ],

                    'formData.story_ids.*' => [
                        'exists:products,id'
                    ],
                ],
                'articles' => [

                    'formData.mode' => [
                        'required',
                        'in:latest,views,random,manual'
                    ],

                    'formData.limit' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],

                    'formData.view' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],

                    'formData.article_ids' => [
                        'required_if:formData.mode,manual',
                        'array'
                    ],

                    'formData.article_ids.*' => [
                        'exists:articles,id'
                    ],
                ],
                'campaigns' => [
                    'formData.campaign_id' => [
                        'required',
                        'exists:campaigns,id'
                    ],
                    'formData.limit' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],
                    'formData.view' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:20'
                    ],
                ],
                'about' => [
                    'formData.description' => [
                        'required',
                    ],
                    'formData.client' => [
                        'required',
                    ],
                    'formData.support' => [
                        'required',
                    ],
                    'formData.shop' => [
                        'required',
                    ],
                    'formData.service' => [
                        'required',
                    ],
                    'formData.images' => [
                        'required',
                        'array',
                        'min:1',
                    ],

                    'formData.images.*' => [
                        'required',
                    ],
                ],

                'contact' => [
                    'formData.mode' => [
                        'required',
                    ],
                ],
                'map' => [
                    'formData.lat' => [
                        'required',
                    ],
                    'formData.long' => [
                        'required',
                    ],
                ],

                'banner' => [

                    'formData.banner_id' => [
                        'required',
                        'exists:banners,id'
                    ],

                ],


                default => [

                ],

            }
        );
    }

    protected function messages(): array
    {
        return [

            // عمومی

            'title.max' =>
                'عنوان نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',


            'layout.grid.default.required' =>
                'عرض موبایل الزامی است.',

            'layout.grid.default.integer' =>
                'عرض موبایل باید عدد باشد.',

            'layout.grid.default.between' =>
                'عرض باید بین ۱ تا ۱۲ باشد.',


            'layout.grid.md.required' =>
                'عرض تبلت الزامی است.',

            'layout.grid.md.integer' =>
                'عرض تبلت باید عدد باشد.',

            'layout.grid.md.between' =>
                'عرض باید بین ۱ تا ۱۲ باشد.',


            'layout.grid.lg.required' =>
                'عرض دسکتاپ الزامی است.',

            'layout.grid.lg.integer' =>
                'عرض دسکتاپ باید عدد باشد.',

            'layout.grid.lg.between' =>
                'عرض باید بین ۱ تا ۱۲ باشد.',


            'layout.spacing.padding_top.integer' =>
                'فاصله بالا باید عدد باشد.',

            'layout.spacing.padding_bottom.integer' =>
                'فاصله پایین باید عدد باشد.',


            'layout.container.required' =>
                'نوع Container را انتخاب کنید.',

            'layout.container.in' =>
                'نوع Container نامعتبر است.',


            // Slider

            'formData.slider_id.required' =>
                'انتخاب اسلایدر الزامی است.',

            'formData.description.required' =>
                'توضیحات الزامی است.',

            'formData.campaign_id.required' =>
                'انتخاب کمپین الزامی است.',

            'formData.slider_id.exists' =>
                'اسلایدر انتخاب شده وجود ندارد.',


            // Products

            'formData.provider.required' =>
                'نوع نمایش محصولات را انتخاب کنید.',

            'formData.images' =>
                'تصویر الزامی می باشد',

            'formData.provider.in' =>
                'نوع نمایش محصولات نامعتبر است.',

            'formData.limit.required' =>
                'تعداد محصولات الزامی است.',

            'formData.limit.integer' =>
                'تعداد محصولات باید عدد باشد.',

            'formData.limit.max' =>
                'حداکثر تعداد محصولات ۵۰ عدد است.',


            // Banner

            'formData.banner_id.required' =>
                'انتخاب بنر الزامی است.',

            'formData.banner_id.exists' =>
                'بنر انتخاب شده وجود ندارد.',

        ];
    }

    public function save()
    {
        abort_if(!auth()->user()->can('sections.create'), 403);
        $data = $this->validate();
        $images=$data['formData']['images'] ?? [];
        unset($data['formData']['images']);
        $data['formData']['title'] = $this->title;
        $payload = [
            'data' => $data['formData'],
            'layout' => $data['layout'],
        ];
        if ($this->selectItem) {
            $item = tap($this->selectItem)->update($payload);
        } else {
            $item = $this->model::create($payload);
        }
        foreach ($images ?? [] as $collection => $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
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

        $data = $this->validate([
            'section_id' => 'required',
            'sort' => 'required|numeric',
        ]);
        $data['page_row_id'] = $this->parentModel->id;
        $section = \App\Models\Section::find($data['section_id']);
        $data['title'] = $section->name;
        $this->model->create($data);
        $this->resetData();
        $this->loadData();
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'سکشن با موفقیت ایجاد شد.'
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
                <button wire:click="resetData()" data-bs-effect="effect-flip-horizontal" data-bs-toggle="modal"
                        href="#add_section" class="btn btn-success-light btn-wave me-0">
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
                        <div class="autoComplete_wrapper" role="combobox" aria-owns="autoComplete_list_1"
                             aria-haspopup="true" aria-expanded="false"><input wire:model.lazy="search"
                                                                               autocapitalize="none" autocomplete="off"
                                                                               class="header-search-bar form-control"
                                                                               id="header-search"
                                                                               placeholder="جستجو برای نتایج..."
                                                                               spellcheck="false" type="text"
                                                                               aria-controls="autoComplete_list_1"
                                                                               aria-autocomplete="both">
                            <ul id="autoComplete_list_1" role="listbox" hidden=""></ul>
                        </div>
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

                                                <a data-bs-toggle="modal" href="#create"
                                                   wire:click="get_data({{$item->id}})" class="text-info fs-14 lh-1"><i
                                                        class="ri-edit-line"></i></a>
                                            @endcan
                                            @can('sections.delete')

                                                <a data-bs-toggle="modal" href="#delete"
                                                   wire:click="get_data({{$item->id}})"
                                                   class="text-danger fs-14 lh-1"><i
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
                                @case('sliders')
                                    @include('dashboard.forms.sliders')
                                    @break

                                @case('categories')
                                    @include('dashboard.forms.categories')
                                    @break

                                @case('instantOffers')
                                    @include('dashboard.forms.instantOffers')
                                    @break

                                @case('products')
                                    @include('dashboard.forms.products')
                                    @break

                                @case('campaigns')
                                    @include('dashboard.forms.campaigns')
                                    @break


                                @case('articles')
                                    @include('dashboard.forms.articles')
                                    @break

                                @case('contact')
                                    @include('dashboard.forms.contact')
                                    @break

                                @case('about')
                                    @include('dashboard.forms.about')
                                    @break

                                @case('map')
                                    @include('dashboard.forms.map')
                                    @break

                                @case('stories')
                                    @include('dashboard.forms.stories')
                                    @break

                                @default
                                    <div class="alert alert-warning">
                                        فرم این سکشن تعریف نشده است.
                                    </div>

                            @endswitch
                        @endif
                        <hr>
                        @include('dashboard.forms.layout')


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
                    <h6 class="modal-title">{{$info['delete'] .' ' .$selectItem?->name}}</h6>
                    <button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">

                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <svg class="flex-shrink-0 me-2 svg-danger" xmlns="http://www.w3.org/2000/svg"
                             enable-background="new 0 0 24 24" height="1.5rem" viewBox="0 0 24 24" width="1.5><0rem"
                             fill="1.5rem" fill="1.5rem" 00
                        " height="24" width="24"/></g>
                        <g>
                            <g>
                                <g>
                                    <path d="M15.73,3H8.27L3,8.27v7.46L8.27,21h7.46L21,15.73V8.27L15.73,3z M19,14.9L14.9,19H9.1L5,14.9V9.1L9.1,5h5.8L19,9.1V14.9z"/>
                                    <rect height="6" width="2" x="11" y="7"/>
                                    <rect height="2" width="2">
                                        <g
                                        ="11">
                                        <div>
                                            از حذف کردن این ایتم مطمین هستید ؟!
                                        </div>
                                    </rect>
                                </g></g></g>
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
