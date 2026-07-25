<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\SeoMeta;
use App\Services\FileUploadService;

new class extends Component
{
    use WithFileUploads;

    public $info = [];
    public $selectItem;
    public $data;
    public $search = '';

    /*
    |--------------------------------------------------------------------------
    | نگاشت نوع → کلاس مدل و برچسب
    |--------------------------------------------------------------------------
    */
    protected array $typeMap = [
        'page'     => ['model' => \App\Models\Page::class,         'label' => 'title', 'fa' => 'صفحه ثابت'],
        'article'  => ['model' => \App\Models\Article::class,      'label' => 'title', 'fa' => 'مقاله'],
        'service'  => ['model' => \App\Models\Service::class,      'label' => 'title', 'fa' => 'خدمات'],
        'category' => ['model' => \App\Models\Category::class,     'label' => 'name',  'fa' => 'دسته‌بندی'],
        'course'   => ['model' => \App\Models\Course::class,       'label' => 'title', 'fa' => 'دوره'],
        'lesson'   => ['model' => \App\Models\CourseLesson::class, 'label' => 'title', 'fa' => 'درس'],
        'faq'      => ['model' => \App\Models\Faq::class,          'label' => 'question', 'fa' => 'سوال'],
    ];

    /*
    |--------------------------------------------------------------------------
    | فیلد انتخاب نوع مدل
    |--------------------------------------------------------------------------
    */
    public string $seoable_type = 'category';
    public $seoable_id = null;

    /*
    |--------------------------------------------------------------------------
    | فیلدهای اصلی SEO
    |--------------------------------------------------------------------------
    */
    public string $title         = '';
    public string $description   = '';
    public string $keywords      = '';
    public string $canonical_url = '';

    public bool $no_index  = false;
    public bool $no_follow = false;
    public bool $no_archive = false;

    /*
    |--------------------------------------------------------------------------
    | Open Graph
    |--------------------------------------------------------------------------
    */
    public string $og_title       = '';
    public string $og_description = '';
    public string $og_type        = 'website';
    public $og_image;

    /*
    |--------------------------------------------------------------------------
    | Twitter Card
    |--------------------------------------------------------------------------
    */
    public string $twitter_title       = '';
    public string $twitter_description = '';
    public string $twitter_card        = 'summary_large_image';
    public $twitter_image;

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    */
    public string $priority   = '0.5';
    public string $changefreq = 'weekly';

    /*
    |--------------------------------------------------------------------------
    | Schema JSON-LD
    |--------------------------------------------------------------------------
    */
    public string $schema = '';

    public SeoMeta $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(SeoMeta $model): void
    {
        abort_if(!auth()->user()->can('seo.view'), 403);

        $this->model = $model;

        $this->info['header']  = 'مدیریت سئو';
        $this->info['create']  = 'افزودن / ویرایش سئو';
        $this->info['delete']  = 'حذف سئو';
        $this->info['personal'] = 'سئو';
        $this->info['table']['headers'] = [
            '#',
            'نوع',
            'صفحه / مقاله',
            'عنوان سئو',
            'noindex',
            'nofollow',
            'اولویت',
            'عملیات',
        ];

        $this->loadData();
    }

    /*
    |--------------------------------------------------------------------------
    | برچسب فارسی برای نوع جاری (استفاده در view)
    |--------------------------------------------------------------------------
    */
    public function getLabelsProperty(): array
    {
        return collect($this->typeMap)
            ->mapWithKeys(fn($v, $k) => [$k => $v['fa']])
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | لیست آیتم‌های قابل انتخاب بر اساس نوع
    |--------------------------------------------------------------------------
    */
    public function getSeoableItemsProperty(): array
    {
        if (!isset($this->typeMap[$this->seoable_type])) {
            return [];
        }

        $modelClass = $this->typeMap[$this->seoable_type]['model'];
        $labelField = $this->typeMap[$this->seoable_type]['label'];

        return $modelClass::query()
            ->select(['id', $labelField])
            ->orderBy($labelField)
            ->pluck($labelField, 'id')
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | تبدیل کلاس کامل → کلید کوتاه (article, page, ...)
    |--------------------------------------------------------------------------
    */
    protected function resolveTypeKey(string $morphClass): string
    {
        foreach ($this->typeMap as $key => $cfg) {
            if ($cfg['model'] === $morphClass) {
                return $key;
            }
        }
        return 'page';
    }

    /*
    |--------------------------------------------------------------------------
    | تبدیل کلید کوتاه → کلاس کامل
    |--------------------------------------------------------------------------
    */
    protected function resolveTypeClass(string $key): string
    {
        return $this->typeMap[$key]['model'] ?? \App\Models\Page::class;
    }

    /*
    |--------------------------------------------------------------------------
    | بارگذاری داده‌ها
    |--------------------------------------------------------------------------
    */
    public function loadData(): void
    {
        $this->data = $this->model
            ->with('seoable')
            ->where(function ($q) {
                $q->where('title', 'LIKE', '%' . $this->search . '%')
                    ->orWhere('description', 'LIKE', '%' . $this->search . '%')
                    ->orWhere('keywords', 'LIKE', '%' . $this->search . '%');
            })
            ->latest()
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | بارگذاری داده رکورد برای ویرایش / حذف
    |--------------------------------------------------------------------------
    */
    public function get_data(int $id): void
    {
        $this->selectItem = $this->model->findOrFail($id);

        // seoable_type در دیتابیس به صورت کلاس کامل ذخیره شده
        $this->seoable_type = $this->resolveTypeKey($this->selectItem->seoable_type);
        $this->seoable_id   = $this->selectItem->seoable_id;

        $this->title         = $this->selectItem->title         ?? '';
        $this->description   = $this->selectItem->description   ?? '';
        $this->keywords      = $this->selectItem->keywords      ?? '';
        $this->canonical_url = $this->selectItem->canonical_url ?? '';

        $this->no_index  = (bool) $this->selectItem->no_index;
        $this->no_follow = (bool) $this->selectItem->no_follow;
        $this->no_archive = (bool) $this->selectItem->no_archive;

        $this->og_title       = $this->selectItem->og_title       ?? '';
        $this->og_description = $this->selectItem->og_description ?? '';
        $this->og_type        = $this->selectItem->og_type        ?? 'website';

        $this->twitter_title       = $this->selectItem->twitter_title       ?? '';
        $this->twitter_description = $this->selectItem->twitter_description ?? '';
        $this->twitter_card        = $this->selectItem->twitter_card        ?? 'summary_large_image';

        $this->priority   = (string) $this->selectItem->priority;
        $this->changefreq = $this->selectItem->changefreq ?? 'weekly';

        $this->schema = $this->selectItem->schema
            ? json_encode($this->selectItem->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';

        // فیلدهای تصویر را ریست می‌کنیم (آپلود جدید اختیاری است)
        $this->og_image      = null;
        $this->twitter_image = null;
    }

    /*
    |--------------------------------------------------------------------------
    | ریست فرم
    |--------------------------------------------------------------------------
    */
    public function resetData(string $action = 'create'): void
    {
        $this->selectItem = null;

        $this->seoable_type = 'category';
        $this->seoable_id   = null;

        $this->title         = '';
        $this->description   = '';
        $this->keywords      = '';
        $this->canonical_url = '';

        $this->no_index  = false;
        $this->no_follow = false;
        $this->no_archive = false;

        $this->og_title       = '';
        $this->og_description = '';
        $this->og_type        = 'website';
        $this->og_image       = null;

        $this->twitter_title       = '';
        $this->twitter_description = '';
        $this->twitter_card        = 'summary_large_image';
        $this->twitter_image       = null;

        $this->priority   = '0.5';
        $this->changefreq = 'weekly';
        $this->schema     = '';

        $this->resetValidation();

        if ($action === 'close') {
            $this->dispatch('close-modal');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | قوانین اعتبارسنجی
    |--------------------------------------------------------------------------
    */
    public function rules(): array
    {
        return [
            'seoable_type' => ['required', 'string', 'in:' . implode(',', array_keys($this->typeMap))],
            'seoable_id'   => ['required', 'integer'],

            'title'         => ['nullable', 'string', 'max:70'],
            'description'   => ['nullable', 'string', 'max:160'],
            'keywords'      => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:500'],

            'no_index'   => ['boolean'],
            'no_follow'  => ['boolean'],
            'no_archive' => ['boolean'],

            'og_title'       => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_type'        => ['nullable', 'string', 'in:article,website,product,profile'],
            'og_image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'twitter_title'       => ['nullable', 'string', 'max:255'],
            'twitter_description' => ['nullable', 'string', 'max:500'],
            'twitter_card'        => ['nullable', 'string', 'in:summary_large_image,summary,app,player'],
            'twitter_image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'priority'    => ['nullable', 'numeric', 'min:0', 'max:1'],
            'changefreq'  => ['nullable', 'string', 'in:always,hourly,daily,weekly,monthly,yearly,never'],
            'schema'      => ['nullable', 'string'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | پیام‌های اعتبارسنجی
    |--------------------------------------------------------------------------
    */
    public function messages(): array
    {
        return [
            'seoable_type.required' => 'نوع صفحه الزامی است.',
            'seoable_type.in'       => 'نوع صفحه معتبر نیست.',
            'seoable_id.required'   => 'انتخاب صفحه یا مقاله الزامی است.',
            'seoable_id.integer'    => 'شناسه صفحه معتبر نیست.',

            'title.max'         => 'عنوان سئو نباید بیشتر از ۷۰ کاراکتر باشد.',
            'description.max'   => 'توضیحات متا نباید بیشتر از ۱۶۰ کاراکتر باشد.',
            'keywords.max'      => 'کلمات کلیدی نباید بیشتر از ۵۰۰ کاراکتر باشد.',
            'canonical_url.url' => 'آدرس canonical معتبر نیست.',

            'og_title.max'       => 'عنوان Open Graph نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'og_description.max' => 'توضیحات Open Graph نباید بیشتر از ۵۰۰ کاراکتر باشد.',
            'og_type.in'         => 'نوع Open Graph معتبر نیست.',
            'og_image.image'     => 'فایل تصویر OG معتبر نیست.',
            'og_image.mimes'     => 'تصویر OG باید با فرمت jpg, jpeg, png یا webp باشد.',
            'og_image.max'       => 'حجم تصویر OG نباید بیشتر از ۲ مگابایت باشد.',

            'twitter_title.max'       => 'عنوان Twitter Card نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'twitter_description.max' => 'توضیحات Twitter Card نباید بیشتر از ۵۰۰ کاراکتر باشد.',
            'twitter_card.in'         => 'نوع Twitter Card معتبر نیست.',
            'twitter_image.image'     => 'فایل تصویر Twitter Card معتبر نیست.',
            'twitter_image.mimes'     => 'تصویر Twitter Card باید با فرمت jpg, jpeg, png یا webp باشد.',
            'twitter_image.max'       => 'حجم تصویر Twitter Card نباید بیشتر از ۲ مگابایت باشد.',

            'priority.numeric' => 'اولویت باید عدد باشد.',
            'priority.min'     => 'اولویت نباید کمتر از ۰ باشد.',
            'priority.max'     => 'اولویت نباید بیشتر از ۱ باشد.',
            'changefreq.in'    => 'فرکانس تغییر معتبر نیست.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ذخیره
    |--------------------------------------------------------------------------
    */
    public function save(): void
    {
        abort_if(!auth()->user()->can('seo.create'), 403);

        $data = $this->validate();

        // تبدیل کلید کوتاه → کلاس کامل برای ذخیره در دیتابیس (morph)
        $data['seoable_type'] = $this->resolveTypeClass($data['seoable_type']);

        // پردازش Schema JSON
        if (!empty($data['schema'])) {
            $decoded = json_decode($data['schema'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('schema', 'فرمت JSON-LD معتبر نیست.');
                return;
            }
            $data['schema'] = $decoded;
        } else {
            $data['schema'] = null;
        }

        // آپلود تصویر OG
        if ($this->og_image) {
            $data['og_image'] = $this->og_image->store('seo/og', 'public');
        } else {
            // اگر ویرایش است و تصویر جدید آپلود نشده، مقدار قبلی را حفظ کن
            unset($data['og_image']);
        }

        // آپلود تصویر Twitter
        if ($this->twitter_image) {
            $data['twitter_image'] = $this->twitter_image->store('seo/twitter', 'public');
        } else {
            unset($data['twitter_image']);
        }
        if ($this->selectItem) {
            $this->selectItem->update($data);
            $message = 'اطلاعات سئو با موفقیت ویرایش شد.';
        } else {
            $this->model->create($data);
            $message = 'سئو جدید با موفقیت ایجاد شد.';
        }

        $this->loadData();
        $this->resetData('close');

        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: $message);
    }

    /*
    |--------------------------------------------------------------------------
    | حذف
    |--------------------------------------------------------------------------
    */
    public function delete(): void
    {
        abort_if(!auth()->user()->can('seo.delete'), 403);

        if (!$this->selectItem) return;

        $item = $this->model->findOrFail($this->selectItem->id);
        $item->delete();

        $this->loadData();
        $this->resetData('close');

        $this->dispatch('alert', type: 'success', title: 'حذف شد', text: 'رکورد سئو با موفقیت حذف شد.');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">{{ $info['header'] }}</h1>
        </div>
        <div class="btn-list">
            <button wire:click="resetData('create')"
                    data-bs-toggle="modal" href="#create"
                    class="btn btn-success-light btn-wave me-0">
                <i class="ri-add-line align-middle"></i>
                {{ $info['create'] }}
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header pb-0 justify-content-between">
                    <div class="card-title">{{ $info['header'] }}</div>
                    <div class="header-element header-search d-md-block d-none my-auto">
                        <input wire:model.lazy="search"
                               wire:keydown.enter="loadData"
                               class="header-search-bar form-control"
                               placeholder="جستجو..."
                               type="text">
                        <a class="header-search-icon border-0" href="javascript:void(0);">
                            <i wire:click="loadData()" class="bi bi-search"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap">
                            <thead>
                            <tr>
                                @foreach($info['table']['headers'] ?? [] as $h)
                                    <th scope="col">{{ $h }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @php($counter = 1)
                            @foreach($data ?? [] as $item)
                                <tr wire:key="{{ $item->id }}">
                                    <th scope="row">{{ $counter }}</th>
                                    <td>
                                        <span class="badge bg-secondary-transparent">
                                            {{ $this->labels[$this->resolveTypeKey($item->seoable_type)] ?? $item->seoable_type }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $item->seoable?->title ?? $item->seoable?->name ?? $item->seoable?->question ?? '—' }}
                                    </td>
                                    <td>{{ $item->title ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $item->no_index ? 'danger' : 'success' }}-transparent">
                                            {{ $item->no_index ? 'بله' : 'خیر' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $item->no_follow ? 'danger' : 'success' }}-transparent">
                                            {{ $item->no_follow ? 'بله' : 'خیر' }}
                                        </span>
                                    </td>
                                    <td>{{ $item->priority }}</td>
                                    <td>
                                        <div class="hstack gap-2 flex-wrap">
                                            <a data-bs-toggle="modal" href="#create"
                                               wire:click="get_data({{ $item->id }})"
                                               class="text-info fs-14 lh-1">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <a data-bs-toggle="modal" href="#delete"
                                               wire:click="get_data({{ $item->id }})"
                                               class="text-danger fs-14 lh-1">
                                                <i class="ri-delete-bin-5-line"></i>
                                            </a>
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

    {{-- ===== Modal: ایجاد / ویرایش ===== --}}
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content modal-content-demo">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h6 class="modal-title">{{ $info['create'] }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body text-start">
                        <div class="row">

                            {{-- انتخاب نوع و صفحه --}}
                            <div class="col-xl-12 mb-3">
                                <div class="p-3 bg-light rounded border">
                                    <h6 class="fw-semibold mb-3 text-muted">انتخاب صفحه یا مقاله</h6>
                                    <div class="row">
                                        <div class="col-xl-4">
                                            <label class="form-label">نوع</label>
                                            <select wire:model.live="seoable_type"
                                                    class="form-select @error('seoable_type') is-invalid @enderror">
                                                @foreach($this->labels as $key => $fa)
                                                    <option value="{{ $key }}">{{ $fa }}</option>
                                                @endforeach
                                            </select>
                                            @error('seoable_type')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-xl-8">
                                            <label class="form-label">
                                                {{ $this->labels[$seoable_type] ?? 'صفحه' }}
                                            </label>
                                            <select  wire:model="seoable_id"
                                                    class=" @error('seoable_id') is-invalid @enderror">
                                                <option value="">انتخاب کنید...</option>
                                                @foreach($this->seoableItems as $id => $label)
                                                    <option value="{{ $id }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('seoable_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Tabs --}}
                            <div class="col-xl-12">
                                <ul class="nav nav-tabs mb-3" wire:ignore>
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#tab-basic">اصلی</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#tab-og">Open Graph</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#tab-twitter">Twitter</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#tab-sitemap">Sitemap</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#tab-schema">Schema</a>
                                    </li>
                                </ul>

                                <div class="tab-content">

                                    {{-- BASIC --}}
                                    <div class="tab-pane fade show active" id="tab-basic">
                                        <div class="row">
                                            <div class="col-xl-12 mb-3">
                                                <label class="form-label">
                                                    Title
                                                    <small class="text-muted">(حداکثر ۷۰ کاراکتر)</small>
                                                </label>
                                                <input wire:model="title"
                                                       maxlength="70"
                                                       class="form-control @error('title') is-invalid @enderror">
                                                @error('title')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-xl-12 mb-3">
                                                <label class="form-label">
                                                    Description
                                                    <small class="text-muted">(حداکثر ۱۶۰ کاراکتر)</small>
                                                </label>
                                                <textarea wire:model="description"
                                                          rows="3" maxlength="160"
                                                          class="form-control @error('description') is-invalid @enderror"></textarea>
                                                @error('description')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-xl-12 mb-3">
                                                <label class="form-label">Keywords</label>
                                                <input wire:model="keywords"
                                                       class="form-control @error('keywords') is-invalid @enderror">
                                            </div>

                                            <div class="col-xl-12 mb-3">
                                                <label class="form-label">Canonical URL</label>
                                                <input wire:model="canonical_url" dir="ltr"
                                                       class="form-control @error('canonical_url') is-invalid @enderror">
                                                @error('canonical_url')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-xl-12">
                                                <label class="form-label d-block">Robots</label>
                                                <label class="me-3">
                                                    <input type="checkbox" wire:model="no_index"> noindex
                                                </label>
                                                <label class="me-3">
                                                    <input type="checkbox" wire:model="no_follow"> nofollow
                                                </label>
                                                <label>
                                                    <input type="checkbox" wire:model="no_archive"> noarchive
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- OG --}}
                                    <div class="tab-pane fade" id="tab-og">
                                        <div class="row">
                                            <div class="col-xl-6 mb-3">
                                                <label class="form-label">OG Title</label>
                                                <input wire:model="og_title" class="form-control">
                                            </div>
                                            <div class="col-xl-6 mb-3">
                                                <label class="form-label">OG Type</label>
                                                <select wire:model="og_type" class="form-control">
                                                    <option value="website">website</option>
                                                    <option value="article">article</option>
                                                    <option value="product">product</option>
                                                    <option value="profile">profile</option>
                                                </select>
                                            </div>
                                            <div class="col-xl-12 mb-3">
                                                <label class="form-label">OG Description</label>
                                                <textarea wire:model="og_description" rows="3"
                                                          class="form-control"></textarea>
                                            </div>
                                            <div class="col-xl-12 mb-3">
                                                <label class="form-label">OG Image</label>
                                                @if($selectItem?->og_image)
                                                    <div class="mb-2">
                                                        <img src="{{ asset('storage/' . $selectItem->og_image) }}"
                                                             alt="og image" height="80" class="rounded">
                                                        <small class="text-muted d-block">آپلود جدید، تصویر قبلی را جایگزین می‌کند.</small>
                                                    </div>
                                                @endif
                                                <input type="file" wire:model="og_image"
                                                       accept="image/jpeg,image/png,image/webp"
                                                       class="form-control @error('og_image') is-invalid @enderror">
                                                @error('og_image')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    {{-- TWITTER --}}
                                    <div class="tab-pane fade" id="tab-twitter">
                                        <div class="row">
                                            <div class="col-xl-6 mb-3">
                                                <label class="form-label">Twitter Title</label>
                                                <input wire:model="twitter_title" class="form-control">
                                            </div>
                                            <div class="col-xl-6 mb-3">
                                                <label class="form-label">Card Type</label>
                                                <select wire:model="twitter_card" class="form-control">
                                                    <option value="summary_large_image">summary_large_image</option>
                                                    <option value="summary">summary</option>
                                                    <option value="app">app</option>
                                                    <option value="player">player</option>
                                                </select>
                                            </div>
                                            <div class="col-xl-12 mb-3">
                                                <label class="form-label">Twitter Description</label>
                                                <textarea wire:model="twitter_description" rows="3"
                                                          class="form-control"></textarea>
                                            </div>
                                            <div class="col-xl-12 mb-3">
                                                <label class="form-label">Twitter Image</label>
                                                @if($selectItem?->twitter_image)
                                                    <div class="mb-2">
                                                        <img src="{{ asset('storage/' . $selectItem->twitter_image) }}"
                                                             alt="twitter image" height="80" class="rounded">
                                                        <small class="text-muted d-block">آپلود جدید، تصویر قبلی را جایگزین می‌کند.</small>
                                                    </div>
                                                @endif
                                                <input type="file" wire:model="twitter_image"
                                                       accept="image/jpeg,image/png,image/webp"
                                                       class="form-control @error('twitter_image') is-invalid @enderror">
                                                @error('twitter_image')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    {{-- SITEMAP --}}
                                    <div class="tab-pane fade" id="tab-sitemap">
                                        <div class="row">
                                            <div class="col-xl-6 mb-3">
                                                <label class="form-label">Priority (0.0 – 1.0)</label>
                                                <input wire:model="priority" type="number"
                                                       step="0.1" min="0" max="1"
                                                       class="form-control @error('priority') is-invalid @enderror">
                                                @error('priority')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-xl-6 mb-3">
                                                <label class="form-label">Change Frequency</label>
                                                <select wire:model="changefreq"
                                                        class="form-control @error('changefreq') is-invalid @enderror">
                                                    @foreach(['always','hourly','daily','weekly','monthly','yearly','never'] as $freq)
                                                        <option value="{{ $freq }}">{{ $freq }}</option>
                                                    @endforeach
                                                </select>
                                                @error('changefreq')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    {{-- SCHEMA --}}
                                    <div class="tab-pane fade" id="tab-schema">
                                        <div class="col-xl-12">
                                            <label class="form-label">JSON-LD Schema</label>
                                            <textarea wire:model="schema" rows="12"
                                                      class="form-control font-monospace @error('schema') is-invalid @enderror"
                                                      dir="ltr"
                                                      placeholder="JSON-LD Schema"></textarea>
                                            @error('schema')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                </div>{{-- /tab-content --}}
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <div wire:loading.remove wire:target="save">
                            <button type="submit" class="btn btn-primary">ذخیره</button>
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                        </div>
                        <div wire:loading wire:target="save" class="spinner-grow text-info" role="status">
                            <span class="visually-hidden">در حال ذخیره...</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===== Modal: حذف ===== --}}
    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $info['delete'] }}</h6>
                    <button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger" role="alert">
                        آیا از حذف این رکورد سئو اطمینان دارید؟
                        @if($selectItem)
                            <br>
                            <strong>{{ $selectItem->title ?? '—' }}</strong>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="delete">
                        <button class="btn btn-danger" wire:click="delete()">حذف</button>
                        <button class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                    <div wire:loading wire:target="delete" class="spinner-grow text-info" role="status">
                        <span class="visually-hidden">در حال حذف...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
