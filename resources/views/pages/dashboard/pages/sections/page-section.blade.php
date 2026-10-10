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
    public $brands;
    public $faqs;
    public RowSection $model;
    // list = صفحه قدیمی لیست سکشن‌های یک ردیف | editor = فقط فرم تنظیمات، داخل صفحه‌ساز بصری
    public string $mode = 'list';

    // سکشن‌هایی که تصویر محصول/دسته‌بندی دارند و تنظیم pictureMode می‌گیرند
    const PICTURE_MODE_SECTIONS = ['categories', 'instantOffers', 'products', 'campaigns', 'brands', 'brandProducts'];
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(PageRow $row, RowSection $model, string $mode = 'list')
    {
        abort_if(!auth()->user()->can('sections.view'), 403);

        $this->mode = $mode === 'editor' ? 'editor' : 'list';

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

    #[\Livewire\Attributes\On('updateOrder')]
    public function updateOrder($ids)
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);

        foreach ($ids as $index => $id) {
            $this->model->where('id', $id)->update([
                'sort' => $index + 1
            ]);
        }
        $this->loadData();
    }
    public function loadForm()
    {
        match ($this->selectItem?->section?->key) {

            'sliders' => $this->loadSliders(),
            'categories' => $this->loadCategories(),
            'products' => $this->loadProductsData(),
            'brands', 'brandProducts' => $this->loadBrandsData(),
            'faq' => $this->loadFaqData(),
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

        $this->brands = \App\Models\Brand::active()
            ->orderBy('sort')
            ->get(['id', 'title']);

    }

    private function loadFaqData()
    {
        $this->faqs = \App\Models\Faq::active()
            ->orderBy('sort_order')
            ->get(['id', 'question']);

        $this->categories = \App\Models\Category::active()
            ->whereHas('faqs')
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    private function loadBrandsData()
    {
        $this->brands = \App\Models\Brand::active()
            ->orderBy('sort')
            ->get(['id', 'title']);
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

        // نمایش مقدار واقعی حالت تصویر (حتی برای سکشن‌های قدیمی بدون pictureMode)
        if (in_array($this->selectItem->section->key, self::PICTURE_MODE_SECTIONS, true)) {
            $this->formData['pictureMode'] = \App\Enums\PictureMode::forSection(
                $this->selectItem->section->key,
                $dbData
            )->value;
        }
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

                'height_mode' => 'fixed',

                'height_mobile' => 200,

                'height_desktop' => 480,

            ],
            'hero' => [
                'style' => 'split',
                'media_position' => 'end',
                'animate' => true,
                'eyebrow' => null,
                'heading' => null,
                'highlight' => null,
                'description' => null,
                'primary_text' => null,
                'primary_link' => null,
                'secondary_text' => null,
                'secondary_link' => null,
                'stat1_value' => null, 'stat1_label' => null,
                'stat2_value' => null, 'stat2_label' => null,
                'stat3_value' => null, 'stat3_label' => null,
                'badge_title' => null,
                'badge_text' => null,
                'video_url' => null,
                'video_on_mobile' => true,
                'overlay_opacity' => 45,
                'height_mobile' => null,
                'height_desktop' => null,
                'images' => [],
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
                'pictureMode' => 'background',

            ],

            'products' => [
                'mode' => 'latest',
                'limit' => 8,
                'view' => 1,
                'layout' => null, // null = پیش‌فرض طرح (نوع ۳ اسلایدر، بقیه زیر هم)
                'pictureMode' => 'background',
                'source' => 'all',
                'brand_id' => null,
                'product_ids' => [],
            ],
            'stories' => [
                'mode' => 'latest',
                'limit' => 8,
                'story_ids' => [],
            ],

            'brands' => [
                'mode' => 'popular',
                'limit' => 6,
                'pictureMode' => 'transparent',
                'brand_ids' => [],
            ],

            'brandProducts' => [
                'mode' => 'latest',
                'limit' => 6,
                'pictureMode' => 'background',
                'brand_ids' => [],
            ],

            \App\Support\Sections\ReturnPolicy::KEY => \App\Support\Sections\ReturnPolicy::defaults(),

            'faq' => [
                'mode' => 'latest',
                'limit' => 8,
                'columns' => 1,
                'description' => null,
                'category_id' => null,
                'faq_ids' => [],
                'open_first' => true,
                'show_search' => false,
                'schema' => true,
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
                'pictureMode' => 'transparent',
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

                    // بدون قانون، validate() این کلیدها را حذف می‌کرد و ذخیره نمی‌شدند
                    'formData.height_mode' => ['nullable', 'in:fixed,max'],
                    'formData.height_mobile' => ['nullable', 'integer', 'min:120', 'max:800'],
                    'formData.height_desktop' => ['nullable', 'integer', 'min:150', 'max:1000'],
                    'formData.autoplay' => ['nullable', 'boolean'],
                    'formData.loop' => ['nullable', 'boolean'],
                    'formData.speed' => ['nullable', 'integer', 'in:3000,5000,7000'],

                ],
                'hero' => [
                    'formData.style' => ['required', 'in:split,overlay'],
                    'formData.media_position' => ['nullable', 'in:start,end'],
                    'formData.animate' => ['nullable', 'boolean'],
                    'formData.eyebrow' => ['nullable', 'string', 'max:80'],
                    'formData.heading' => ['required', 'string', 'max:120'],
                    'formData.highlight' => ['nullable', 'string', 'max:60'],
                    'formData.description' => ['nullable', 'string', 'max:400'],
                    'formData.primary_text' => ['nullable', 'string', 'max:40', 'required_with:formData.primary_link'],
                    'formData.primary_link' => ['nullable', 'string', 'max:500', 'required_with:formData.primary_text', 'regex:/^(https?:\/\/|\/|#)/i'],
                    'formData.secondary_text' => ['nullable', 'string', 'max:40', 'required_with:formData.secondary_link'],
                    'formData.secondary_link' => ['nullable', 'string', 'max:500', 'required_with:formData.secondary_text', 'regex:/^(https?:\/\/|\/|#)/i'],
                    'formData.stat1_value' => ['nullable', 'string', 'max:20'],
                    'formData.stat1_label' => ['nullable', 'string', 'max:40'],
                    'formData.stat2_value' => ['nullable', 'string', 'max:20'],
                    'formData.stat2_label' => ['nullable', 'string', 'max:40'],
                    'formData.stat3_value' => ['nullable', 'string', 'max:20'],
                    'formData.stat3_label' => ['nullable', 'string', 'max:40'],
                    'formData.badge_title' => ['nullable', 'string', 'max:60'],
                    'formData.badge_text' => ['nullable', 'string', 'max:80'],
                    'formData.video_url' => ['nullable', 'url:https,http', 'max:1000'],
                    'formData.video_on_mobile' => ['nullable', 'boolean'],
                    'formData.overlay_opacity' => ['nullable', 'integer', 'min:0', 'max:90'],
                    'formData.height_mobile' => ['nullable', 'integer', 'min:240', 'max:1000'],
                    'formData.height_desktop' => ['nullable', 'integer', 'min:320', 'max:1200'],
                    // تصویر حداکثر ۵ و ویدیو حداکثر ۳۰ مگابایت
                    'formData.images.hero_media' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm', 'max:30720',
                        function ($attribute, $value, $fail) {
                            if ($value instanceof UploadedFile && str_starts_with((string) $value->getMimeType(), 'image/') && $value->getSize() > 5 * 1024 * 1024) {
                                $fail('حجم تصویر هیرو نباید بیشتر از ۵ مگابایت باشد.');
                            }
                        }],
                    'formData.images.hero_poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
                    // بدون قانون، validate() این کلید را حذف و ذخیره نمی‌کرد
                    'formData.layout' => ['nullable', 'in:grid,slider'],
                    'formData.pictureMode' => [
                        'nullable',
                    ],

                    'formData.source' => [
                        'required',
                        'in:all,brand'
                    ],

                    'formData.brand_id' => [
                        'nullable',
                        'required_if:formData.source,brand',
                        'exists:brands,id'
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
                'brands' => [
                    'formData.mode' => [
                        'required',
                        'in:popular,latest,all,manual'
                    ],

                    'formData.limit' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:30'
                    ],

                    'formData.brand_ids' => [
                        'required_if:formData.mode,manual',
                        'array'
                    ],

                    'formData.brand_ids.*' => [
                        'exists:brands,id'
                    ],
                ],
                \App\Support\Sections\ReturnPolicy::KEY => \App\Support\Sections\ReturnPolicy::rules(),

                'faq' => [
                    'formData.mode' => [
                        'required',
                        'in:latest,category,manual'
                    ],

                    'formData.limit' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:50'
                    ],

                    'formData.columns' => [
                        'required',
                        'in:1,2'
                    ],

                    'formData.description' => [
                        'nullable',
                        'string',
                        'max:255'
                    ],

                    'formData.category_id' => [
                        'nullable',
                        'required_if:formData.mode,category',
                        'exists:categories,id'
                    ],

                    'formData.faq_ids' => [
                        'required_if:formData.mode,manual',
                        'array'
                    ],

                    'formData.faq_ids.*' => [
                        'exists:faqs,id'
                    ],

                    'formData.open_first' => ['boolean'],
                    'formData.show_search' => ['boolean'],
                    'formData.schema' => ['boolean'],
                ],
                'brandProducts' => [
                    'formData.mode' => [
                        'required',
                        'in:latest,sales,views,random'
                    ],

                    'formData.limit' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:30'
                    ],

                    'formData.brand_ids' => [
                        'nullable',
                        'array'
                    ],

                    'formData.brand_ids.*' => [
                        'exists:brands,id'
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

            },

            in_array($this->selectItem?->section->key, self::PICTURE_MODE_SECTIONS, true)
                ? $this->pictureModeRules()
                : []
        );
    }

    protected function pictureModeRules(): array
    {
        return [
            'formData.pictureMode' => [
                'required',
                \Illuminate\Validation\Rule::in(\App\Enums\PictureMode::values()),
            ],
        ];
    }

    protected function messages(): array
    {
        return \App\Support\Sections\ReturnPolicy::messages() + [

            // عمومی

            'formData.faq_ids.required_if' =>
                'در حالت انتخاب دستی، انتخاب حداقل یک سوال الزامی است.',

            'formData.faq_ids.*.exists' =>
                'سوال انتخاب‌شده معتبر نیست.',

            'formData.category_id.required_if' =>
                'انتخاب دسته‌بندی الزامی است.',

            'formData.category_id.exists' =>
                'دسته‌بندی انتخاب‌شده معتبر نیست.',

            'formData.columns.in' =>
                'چیدمان انتخاب‌شده معتبر نیست.',

            'formData.description.max' =>
                'توضیح نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',

            'formData.brand_ids.required_if' =>
                'در حالت انتخاب دستی، انتخاب حداقل یک برند الزامی است.',

            'formData.brand_ids.*.exists' =>
                'برند انتخاب‌شده معتبر نیست.',

            'formData.source.required' =>
                'انتخاب منبع محصولات الزامی است.',

            'formData.source.in' =>
                'منبع محصولات معتبر نیست.',

            'formData.brand_id.required_if' =>
                'انتخاب برند الزامی است.',

            'formData.brand_id.exists' =>
                'برند انتخاب‌شده معتبر نیست.',

            'formData.pictureMode.required' =>
                'انتخاب حالت نمایش تصاویر الزامی است.',

            'formData.pictureMode.in' =>
                'حالت نمایش تصاویر معتبر نیست.',

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

            // Hero
            'formData.style.required' => 'سبک نمایش هیرو را انتخاب کنید.',
            'formData.style.in' => 'سبک نمایش هیرو نامعتبر است.',
            'formData.heading.required' => 'عنوان اصلی هیرو الزامی است.',
            'formData.heading.max' => 'عنوان اصلی نباید بیشتر از ۱۲۰ کاراکتر باشد.',
            'formData.eyebrow.max' => 'متن بالای عنوان نباید بیشتر از ۸۰ کاراکتر باشد.',
            'formData.highlight.max' => 'بخش برجسته نباید بیشتر از ۶۰ کاراکتر باشد.',
            'formData.description.max' => 'توضیح نباید بیشتر از ۴۰۰ کاراکتر باشد.',
            'formData.primary_text.required_with' => 'برای دکمه اصلی متن را وارد کنید.',
            'formData.primary_link.required_with' => 'برای دکمه اصلی لینک را وارد کنید.',
            'formData.primary_link.regex' => 'لینک دکمه اصلی باید با / یا http(s):// شروع شود.',
            'formData.secondary_text.required_with' => 'برای دکمه دوم متن را وارد کنید.',
            'formData.secondary_link.required_with' => 'برای دکمه دوم لینک را وارد کنید.',
            'formData.secondary_link.regex' => 'لینک دکمه دوم باید با / یا http(s):// شروع شود.',
            'formData.primary_text.max' => 'متن دکمه نباید بیشتر از ۴۰ کاراکتر باشد.',
            'formData.secondary_text.max' => 'متن دکمه نباید بیشتر از ۴۰ کاراکتر باشد.',
            'formData.stat1_value.max' => 'مقدار آمار نباید بیشتر از ۲۰ کاراکتر باشد.',
            'formData.stat2_value.max' => 'مقدار آمار نباید بیشتر از ۲۰ کاراکتر باشد.',
            'formData.stat3_value.max' => 'مقدار آمار نباید بیشتر از ۲۰ کاراکتر باشد.',
            'formData.badge_title.max' => 'عنوان کارت شناور نباید بیشتر از ۶۰ کاراکتر باشد.',
            'formData.badge_text.max' => 'توضیح کارت شناور نباید بیشتر از ۸۰ کاراکتر باشد.',
            'formData.video_url.url' => 'لینک ویدیو باید یک آدرس کامل http(s) باشد.',
            'formData.overlay_opacity.max' => 'تیرگی حداکثر ۹۰٪ است.',
            'formData.height_mobile.min' => 'ارتفاع موبایل خارج از محدوده مجاز است.',
            'formData.height_desktop.min' => 'ارتفاع دسکتاپ خارج از محدوده مجاز است.',
            'formData.images.hero_media.mimetypes' => 'فایل اصلی باید تصویر (jpg, png, webp) یا ویدیو (mp4, webm) باشد.',
            'formData.images.hero_media.max' => 'حجم ویدیو نباید بیشتر از ۳۰ مگابایت باشد.',
            'formData.images.hero_poster.image' => 'پوستر باید تصویر باشد.',
            'formData.images.hero_poster.mimes' => 'پوستر باید jpg، png یا webp باشد.',
            'formData.images.hero_poster.max' => 'حجم پوستر نباید بیشتر از ۵ مگابایت باشد.',

            'formData.layout.in' => 'چیدمان محصولات نامعتبر است.',
            'formData.height_mode.in' => 'نوع ارتفاع اسلایدر نامعتبر است.',
            'formData.height_mobile.integer' => 'ارتفاع موبایل باید عدد صحیح باشد.',
            'formData.height_mobile.min' => 'ارتفاع موبایل حداقل ۱۲۰ پیکسل است.',
            'formData.height_mobile.max' => 'ارتفاع موبایل حداکثر ۸۰۰ پیکسل است.',
            'formData.height_desktop.integer' => 'ارتفاع دسکتاپ باید عدد صحیح باشد.',
            'formData.height_desktop.min' => 'ارتفاع دسکتاپ حداقل ۱۵۰ پیکسل است.',
            'formData.height_desktop.max' => 'ارتفاع دسکتاپ حداکثر ۱۰۰۰ پیکسل است.',
            'formData.speed.in' => 'سرعت تغییر اسلاید نامعتبر است.',


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
        abort_if(!auth()->user()->can($this->selectItem ? 'sections.edit' : 'sections.create'), 403);
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

        // صفحه‌ساز بصری کارت این سکشن را بروز می‌کند
        $this->dispatch('builder-section-saved', id: $item->id);
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
        $item = $this->model->create($data);
        $this->resetData('close');
        $this->loadData();
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'سکشن با موفقیت ایجاد شد. اکنون تنظیمات و محتوای آن را وارد کنید.'
        );

        // بلافاصله فرم تنظیمات همان سکشن باز می‌شود تا محتوای آن وارد شود
        $this->get_data($item->id);
        $this->dispatch('open-section-settings');
    }

    /**
     * افزودن/حذف آیتم در لیست‌های قابل تکرار فرم سکشن (مثل شرایط مرجوعی)
     */
    /**
     * صفحه‌ساز بصری: باز کردن فرم تنظیمات یک سکشن
     */
    #[\Livewire\Attributes\On('builder-edit-section')]
    public function editFromBuilder(int $id): void
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);

        $this->resetValidation();
        $this->get_data($id);
        $this->dispatch('open-section-settings');
    }

    /**
     * حذف تصویر/ویدیو یا پوستر سکشن هیرو
     */
    public function removeHeroMedia(string $collection): void
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);
        abort_unless(in_array($collection, ['hero_media', 'hero_poster'], true) && $this->selectItem, 404);

        $this->selectItem->media()->where('collection', $collection)->get()->each(fn ($m) => $this->deleteMedia($m));
        $this->selectItem->load('media');
        unset($this->formData['images'][$collection]);

        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'فایل حذف شد.');
    }

    protected function repeatableLists(): array
    {
        return match ($this->selectItem?->section?->key) {
            \App\Support\Sections\ReturnPolicy::KEY => \App\Support\Sections\ReturnPolicy::LISTS,
            default => [],
        };
    }

    public function addListItem(string $key): void
    {
        $lists = $this->repeatableLists();

        if (!array_key_exists($key, $lists)) {
            return;
        }

        $items = array_values((array) ($this->formData[$key] ?? []));

        if (count($items) >= \App\Support\Sections\ReturnPolicy::MAX_ITEMS) {
            return;
        }

        $items[] = $lists[$key];
        $this->formData[$key] = $items;
    }

    public function removeListItem(string $key, int $index): void
    {
        if (!array_key_exists($key, $this->repeatableLists())) {
            return;
        }

        $items = (array) ($this->formData[$key] ?? []);
        unset($items[$index]);

        $this->formData[$key] = array_values($items);
        $this->resetValidation('formData.' . $key . '.*');
    }


};
?>
<div>
    @if($mode === 'list')
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
                            <tbody id="simple-list">
                            @php($counter=1)
                            @foreach($data ?? [] as $item)
                                <tr data-id="{{ $item->id }}" wire:key="{{$item->id}}">
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
    @endif
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

                                @case('brands')
                                    @include('dashboard.forms.brands')
                                    @break

                                @case('brandProducts')
                                    @include('dashboard.forms.brandProducts')
                                    @break

                                @case('faq')
                                    @include('dashboard.forms.faq')
                                    @break

                                @case('returnPolicy')
                                    @include('dashboard.forms.returnPolicy')
                                    @break

                                @case('hero')
                                    @include('dashboard.forms.hero')
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
    @if($mode === 'list')
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
    @endif
    @push('scripts')

        <script src="{{asset('dashboard')}}/libs/sortablejs/Sortable.min.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const simple = document.getElementById('simple-list');
                if (!simple) return; // حالت editor (داخل صفحه‌ساز بصری) لیست ندارد
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

            // بعد از افزودن سکشن، فرم تنظیمات همان سکشن باز می‌شود
            (function () {
                const register = () => Livewire.on('open-section-settings', () => {
                    // صبر تا بسته شدن کامل مودال افزودن
                    setTimeout(() => {
                        const el = document.getElementById('create');
                        if (el) bootstrap.Modal.getOrCreateInstance(el).show();
                    }, 400);
                });

                window.Livewire ? register() : document.addEventListener('livewire:init', register);
            })();
        </script>
    @endpush

</div>
