<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\Category;
use App\Models\Product;
use App\Models\Brand;
use App\Models\OptionValue;
use App\Models\ProductVariant;
use App\Services\Catalog\CategoryFilterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/*
|--------------------------------------------------------------------
| صفحه دسته‌بندی با فیلترهای پویا
|--------------------------------------------------------------------
| فیلترها از «ویژگی‌های دسته‌بندی» در پنل (category_attributes) ساخته می‌شوند و به زیر‌دسته‌ها
| ارث می‌رسند؛ بدون تعریف، از مشخصات «قابل فیلتر» و ویژگی‌های قیمت‌ساز محصولات همین دسته.
| همه فیلترها، تعداد هر گزینه، مرتب‌سازی و صفحه‌بندی روی ایندکس محصولات دسته
| (CategoryFilterService) و قیمت نهایی موتور قیمت انجام می‌شود؛ پس تعداد کل و صفحه‌ها دقیق‌اند.
| وضعیت فیلترها در آدرس صفحه می‌ماند (رفرش، صفحه‌بندی و اشتراک لینک).
*/
new class extends Component
{
    use WithPagination;

    public const PER_PAGE = 12;

    public const SORTS = [
        'latest' => 'جدیدترین',
        'sales' => 'پرفروش‌ترین',
        'cheap' => 'ارزان‌ترین',
        'expensive' => 'گران‌ترین',
        'discount' => 'بیشترین تخفیف',
        'rating' => 'محبوب‌ترین',
    ];

    public Category $category;
    public Collection $childCategories;
    public array $categoryIds = [];

    #[Url(except: 'latest')]
    public string $sort = 'latest';

    #[Url(as: 'cat', except: [])]
    public array $selectedCategories = [];

    #[Url(as: 'brand', except: [])]
    public array $selectedBrands = [];

    #[Url(as: 'stock', except: false)]
    public bool $onlyInStock = false;

    #[Url(as: 'off', except: false)]
    public bool $onlyDiscounted = false;

    #[Url(as: 'new', except: false)]
    public bool $onlyNew = false;

    #[Url(as: 'rate', except: null)]
    public ?int $minRating = null;

    #[Url(as: 'min', except: null)]
    public ?int $minPrice = null;

    #[Url(as: 'max', except: null)]
    public ?int $maxPrice = null;

    // مشخصات فنی: specId => [values] | ['min' => , 'max' => ] | true
    #[Url(as: 'f', except: [])]
    public array $specs = [];

    // ویژگی‌های قیمت‌ساز (رنگ، سایز): optionId => [valueIds]
    #[Url(as: 'o', except: [])]
    public array $options = [];

    protected $paginationTheme = 'tailwind';

    public function mount($slug)
    {
        $this->category = Category::query()->active()->where('slug', $slug)->firstOrFail();

        $this->childCategories = $this->category->children()->active()->orderBy('title')->get();

        $this->categoryIds = $this->category->getAllDescendantIds()
            ->push($this->category->id)
            ->unique()
            ->values()
            ->toArray();

        if (!array_key_exists($this->sort, self::SORTS)) {
            $this->sort = 'latest';
        }
    }

    protected function service(): CategoryFilterService
    {
        return app(CategoryFilterService::class);
    }

    /*
    |--------------------------------------------------------------------------
    | داده
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function index(): array
    {
        return $this->service()->index($this->categoryIds);
    }

    #[Computed]
    public function definitions(): Collection
    {
        return $this->service()->definitions($this->category, $this->index);
    }

    /** شناسه هر زیر‌دسته => خودش و همه زیر‌شاخه‌هایش */
    #[Computed]
    public function childScopes(): array
    {
        return $this->childCategories->mapWithKeys(fn ($child) => [
            $child->id => $child->getAllDescendantIds()->push($child->id)->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ])->all();
    }

    protected function state(): array
    {
        return [
            'categories' => $this->selectedCategories,
            'brands' => $this->selectedBrands,
            'stock' => $this->onlyInStock,
            'discount' => $this->onlyDiscounted,
            'new' => $this->onlyNew,
            'rating' => $this->minRating,
            'price_min' => $this->minPrice,
            'price_max' => $this->maxPrice,
            'specs' => $this->specs,
            'options' => $this->options,
            'sort' => $this->sort,
        ];
    }

    #[Computed]
    public function result(): array
    {
        return $this->service()->apply($this->index, $this->definitions, $this->state(), $this->childScopes);
    }

    #[Computed]
    public function facets(): array
    {
        return $this->result['facets'];
    }

    #[Computed]
    public function brands(): Collection
    {
        $ids = collect($this->index)->pluck('brand')->filter()->unique();

        return $ids->isEmpty() ? collect() : Brand::where('status', 1)->whereIn('id', $ids)->orderBy('title')->get(['id', 'title']);
    }

    /** مقادیر قابل انتخاب هر ویژگی (با برچسب، رنگ و تعداد) */
    #[Computed]
    public function attributeOptions(): array
    {
        $facets = $this->facets['attributes'];
        $result = [];

        $optionValueIds = $this->definitions->where('kind', 'option')
            ->flatMap(fn ($d) => array_keys($facets[$d['key']] ?? []))
            ->merge(collect($this->options)->flatten())
            ->unique()->all();

        $optionValues = $optionValueIds ? OptionValue::whereIn('id', $optionValueIds)->orderBy('sort')->orderBy('id')->get()->keyBy('id') : collect();

        foreach ($this->definitions as $definition) {
            $key = $definition['key'];

            if ($definition['kind'] === 'option') {
                $selected = array_map('intval', (array) ($this->options[$definition['id']] ?? []));
                $counts = $facets[$key] ?? [];

                $values = $optionValues->filter(fn ($v) => (int) $v->option_id === $definition['id'] && (isset($counts[$v->id]) || in_array($v->id, $selected, true)))
                    ->map(fn ($v) => [
                        'value' => $v->id,
                        'label' => $v->title,
                        'count' => (int) ($counts[$v->id] ?? 0),
                        'selected' => in_array($v->id, $selected, true),
                        'color' => $definition['display'] === 'color' ? $this->service()->colorFor($v) : null,
                    ])->values()->all();
            } elseif (in_array($definition['display'], ['range', 'toggle'], true)) {
                $values = $facets[$key] ?? null;
            } else {
                $selected = array_map('strval', (array) ($this->specs[$definition['id']] ?? []));
                $counts = $facets[$key] ?? [];

                foreach ($selected as $value) {
                    $counts[$value] ??= 0;
                }

                $values = collect($counts)->map(fn ($count, $value) => [
                    'value' => (string) $value,
                    'label' => is_numeric($value) ? number_format((float) $value, str_contains((string) $value, '.') ? 1 : 0) : (string) $value,
                    'count' => (int) $count,
                    'selected' => in_array((string) $value, $selected, true),
                ])->sortBy(fn ($v) => is_numeric($v['value']) ? sprintf('%020.4f', (float) $v['value']) : $v['label'])->values()->all();
            }

            // فیلتر بدون گزینه قابل انتخاب نمایش داده نمی‌شود
            $empty = is_array($values) && array_is_list($values) ? !$values
                : ($definition['display'] === 'range' ? ($values['min'] ?? null) === null || $values['min'] == $values['max']
                    : ($definition['display'] === 'toggle' ? !$values && empty($this->specs[$definition['id']]) : false));

            if (!$empty) {
                $result[$key] = ['definition' => $definition, 'values' => $values];
            }
        }

        return $result;
    }

    #[Computed]
    public function priceBounds(): array
    {
        $bounds = $this->result['bounds']['price'];

        return ['min' => (int) floor(($bounds['min'] ?? 0) / 1000) * 1000, 'max' => (int) ceil(($bounds['max'] ?? 0) / 1000) * 1000];
    }

    #[Computed]
    public function paginator(): LengthAwarePaginator
    {
        $ids = $this->result['ids'];
        $page = max(1, min($this->getPage(), (int) ceil(max(1, count($ids)) / self::PER_PAGE)));
        $pageIds = array_slice($ids, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $products = $pageIds
            ? Product::with(['media', 'brand'])->whereIn('id', $pageIds)->get()->keyBy('id')
            : collect();

        $items = collect($pageIds)->map(fn ($id) => $products->get($id))->filter()->values();

        return new LengthAwarePaginator($items, count($ids), self::PER_PAGE, $page, [
            'path' => route('categories.show', $this->category->slug),
            'pageName' => 'page',
        ]);
    }

    #[Computed]
    public function favoriteProductIds(): array
    {
        if (!Auth::check()) {
            return [];
        }

        return DB::table('wishlists')
            ->where('user_id', Auth::id())
            ->whereIn('product_id', $this->paginator->pluck('id'))
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** ردیف‌های کارت محصول (قیمت/موجودی از همان ایندکس فیلترها) */
    #[Computed]
    public function viewProducts(): Collection
    {
        $variants = ProductVariant::whereIn('id', $this->paginator->getCollection()->map(fn ($p) => $this->index[$p->id]['variant'] ?? null)->filter())
            ->with('product:id,slug')
            ->get()
            ->keyBy('id');

        return $this->paginator->getCollection()->map(function ($product) use ($variants) {
            $row = $this->index[$product->id] ?? [];
            $image = $product->media->firstWhere('collection', 'featured_image');

            $imageStyle = $image && in_array(strtolower((string) $image->mime_type), ['image/png', 'image/webp', 'image/gif'], true)
                ? 'transparent'
                : 'background';

            return (object) [
                'product' => $product,
                'variant' => $variants->get($row['variant'] ?? 0),
                'price' => (int) ($row['base'] ?? 0),
                'compare_price' => null,
                'final_price' => (int) ($row['price'] ?? 0),
                'discount_percent' => (int) ($row['discount'] ?? 0),
                'stock' => (int) ($row['stock'] ?? 0),
                'image' => $image,
                'image_style' => $imageStyle,
                'is_favorited' => in_array($product->id, $this->favoriteProductIds, true),
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | فیلترهای فعال (Chip)
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function activeFilters(): array
    {
        $chips = [];

        foreach ($this->selectedCategories as $id) {
            if ($child = $this->childCategories->firstWhere('id', (int) $id)) {
                $chips[] = ['label' => $child->title, 'type' => 'category', 'key' => (int) $id, 'value' => null];
            }
        }

        foreach ($this->selectedBrands as $id) {
            if ($brand = $this->brands->firstWhere('id', (int) $id)) {
                $chips[] = ['label' => 'برند: ' . $brand->title, 'type' => 'brand', 'key' => (int) $id, 'value' => null];
            }
        }

        if ($this->minPrice !== null || $this->maxPrice !== null) {
            $chips[] = ['label' => 'قیمت: ' . number_format($this->minPrice ?? $this->priceBounds['min']) . ' تا ' . number_format($this->maxPrice ?? $this->priceBounds['max']), 'type' => 'price', 'key' => null, 'value' => null];
        }

        foreach (['onlyInStock' => 'فقط موجود', 'onlyDiscounted' => 'تخفیف‌دار', 'onlyNew' => 'محصولات جدید'] as $property => $label) {
            if ($this->{$property}) {
                $chips[] = ['label' => $label, 'type' => 'flag', 'key' => $property, 'value' => null];
            }
        }

        if ($this->minRating) {
            $chips[] = ['label' => 'امتیاز ' . $this->minRating . ' به بالا', 'type' => 'rating', 'key' => null, 'value' => null];
        }

        $definitions = $this->definitions->keyBy('key');

        foreach ($this->specs as $specId => $selection) {
            $definition = $definitions->get('s' . $specId);
            if (!$definition || $selection === [] || $selection === null || $selection === '') {
                continue;
            }

            if ($definition['display'] === 'range') {
                $chips[] = ['label' => $definition['title'] . ': ' . ($selection['min'] ?? '…') . ' تا ' . ($selection['max'] ?? '…'), 'type' => 'spec', 'key' => (int) $specId, 'value' => null];
            } elseif ($definition['display'] === 'toggle') {
                $chips[] = ['label' => $definition['title'], 'type' => 'spec', 'key' => (int) $specId, 'value' => null];
            } else {
                foreach ((array) $selection as $value) {
                    $chips[] = ['label' => $definition['title'] . ': ' . $value, 'type' => 'spec', 'key' => (int) $specId, 'value' => (string) $value];
                }
            }
        }

        $valueTitles = OptionValue::whereIn('id', collect($this->options)->flatten()->map(fn ($v) => (int) $v)->all() ?: [0])->pluck('title', 'id');

        foreach ($this->options as $optionId => $valueIds) {
            $definition = $definitions->get('o' . $optionId);
            foreach ((array) $valueIds as $valueId) {
                if ($definition && isset($valueTitles[(int) $valueId])) {
                    $chips[] = ['label' => $definition['title'] . ': ' . $valueTitles[(int) $valueId], 'type' => 'option', 'key' => (int) $optionId, 'value' => (string) $valueId];
                }
            }
        }

        return $chips;
    }

    /*
    |--------------------------------------------------------------------------
    | اکشن‌ها (هر تغییر => صفحه ۱)
    |--------------------------------------------------------------------------
    */

    /** نتیجه‌های محاسبه‌شده با وضعیت قبلی فیلترها کنار گذاشته می‌شوند */
    protected function flush(): void
    {
        unset($this->result, $this->facets, $this->attributeOptions, $this->priceBounds, $this->paginator, $this->viewProducts, $this->favoriteProductIds, $this->activeFilters);
    }

    // هر تغییر صفحه (از جمله resetPage پس از تغییر فیلتر) => محاسبه تازه
    public function updatedPaginators($page, $pageName): void
    {
        $this->flush();
    }
    public function updated($property): void
    {
        if (in_array(explode('.', $property)[0], ['selectedCategories', 'selectedBrands', 'onlyInStock', 'onlyDiscounted', 'onlyNew', 'minRating', 'minPrice', 'maxPrice', 'specs', 'options'], true)) {
            $this->flush();
            $this->resetPage();
        }
    }

    public function setSort(string $sort): void
    {
        $this->sort = array_key_exists($sort, self::SORTS) ? $sort : 'latest';
        $this->resetPage();
    }

    public function updatePriceRange($min, $max): void
    {
        $bounds = $this->priceBounds;
        $min = is_numeric($min) ? max($bounds['min'], (int) $min) : null;
        $max = is_numeric($max) ? min($bounds['max'], (int) $max) : null;

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        // بازه کامل = بدون فیلتر قیمت
        $this->minPrice = $min !== null && $min > $bounds['min'] ? $min : null;
        $this->maxPrice = $max !== null && $max < $bounds['max'] ? $max : null;
        $this->resetPage();
    }

    public function setRating(?int $rating): void
    {
        $this->minRating = $this->minRating === $rating ? null : $rating;
        $this->resetPage();
    }

    public function toggleOption(int $optionId, int $valueId): void
    {
        $values = array_map('intval', (array) ($this->options[$optionId] ?? []));
        $values = in_array($valueId, $values, true) ? array_values(array_diff($values, [$valueId])) : [...$values, $valueId];

        if ($values) {
            $this->options[$optionId] = $values;
        } else {
            unset($this->options[$optionId]);
        }

        $this->resetPage();
    }

    /** فیلتر کشویی ویژگی قیمت‌ساز: فقط یک مقدار */
    public function toggleOptionSingle(int $optionId, int $valueId): void
    {
        $this->options[$optionId] = [$valueId];
        $this->resetPage();
    }

    public function toggleSpecValue(int $specId, string $value, bool $single = false): void
    {
        $values = array_map('strval', (array) ($this->specs[$specId] ?? []));

        if ($single) {
            $values = in_array($value, $values, true) ? [] : [$value];
        } else {
            $values = in_array($value, $values, true) ? array_values(array_diff($values, [$value])) : [...$values, $value];
        }

        if ($values) {
            $this->specs[$specId] = $values;
        } else {
            unset($this->specs[$specId]);
        }

        $this->resetPage();
    }

    public function setSpecRange(int $specId, $min, $max): void
    {
        $bounds = $this->attributeOptions['s' . $specId]['values'] ?? null;
        $min = is_numeric($min) ? (float) $min : null;
        $max = is_numeric($max) ? (float) $max : null;

        if ($bounds && $min !== null && $max !== null && $min <= $bounds['min'] && $max >= $bounds['max']) {
            unset($this->specs[$specId]);
        } else {
            $this->specs[$specId] = array_filter(['min' => $min, 'max' => $max], fn ($v) => $v !== null);
        }

        $this->resetPage();
    }

    public function toggleSpecFlag(int $specId): void
    {
        if (!empty($this->specs[$specId])) {
            unset($this->specs[$specId]);
        } else {
            $this->specs[$specId] = true;
        }

        $this->resetPage();
    }

    public function removeFilter(string $type, $key = null, $value = null): void
    {
        match ($type) {
            'category' => $this->selectedCategories = array_values(array_diff(array_map('intval', $this->selectedCategories), [(int) $key])),
            'brand' => $this->selectedBrands = array_values(array_diff(array_map('intval', $this->selectedBrands), [(int) $key])),
            'price' => [$this->minPrice, $this->maxPrice] = [null, null],
            'flag' => in_array($key, ['onlyInStock', 'onlyDiscounted', 'onlyNew'], true) ? $this->{$key} = false : null,
            'rating' => $this->minRating = null,
            'spec' => $value === null ? $this->removeSpec((int) $key) : $this->toggleSpecValue((int) $key, (string) $value),
            'option' => $this->toggleOption((int) $key, (int) $value),
            default => null,
        };

        $this->resetPage();
    }

    protected function removeSpec(int $specId): void
    {
        unset($this->specs[$specId]);
    }

    public function clearFilters(): void
    {
        $this->reset(['selectedCategories', 'selectedBrands', 'onlyInStock', 'onlyDiscounted', 'onlyNew', 'minRating', 'minPrice', 'maxPrice', 'specs', 'options']);
        $this->resetPage();
    }

    /*
    |--------------------------------------------------------------------------
    | علاقه‌مندی و سبد خرید
    |--------------------------------------------------------------------------
    */
    public function toggleFavorite(int $productId): void
    {
        if (!Auth::check()) {
            $this->dispatch('alert', type: 'error', message: 'برای افزودن به علاقه‌مندی ابتدا وارد شوید.');
            return;
        }

        if (!Product::active()->whereKey($productId)->exists()) {
            return;
        }

        $existing = Auth::user()->wishlists()->where('product_id', $productId)->first();

        $existing
            ? $existing->delete()
            : Auth::user()->wishlists()->createOrFirst(['product_id' => $productId]);

        unset($this->favoriteProductIds, $this->viewProducts);
    }

    public function addToCart(int $variantId): void
    {
        if (!Auth::check()) {
            $this->dispatch('alert', type: 'error', message: 'برای خرید ابتدا وارد شوید.');
            return;
        }

        $variant = ProductVariant::query()->with('product')->find($variantId);

        if (!$variant) {
            $this->dispatch('alert', type: 'error', message: 'این کالا در دسترس نیست.');
            return;
        }

        $finalPrice = (int) (rescue(fn () => $variant->priceData(), [], false)['after_discount'] ?? $variant->price);

        $result = app(\App\Services\Cart\CartService::class)->add(Auth::id(), $variant->product_id, $variant->id, $finalPrice);

        if (!$result['ok']) {
            $this->dispatch('alert', type: 'error', message: $result['message']);
            return;
        }

        $this->dispatch('cart-updated');
        $this->dispatch('alert', type: 'success', message: $result['message']);
    }
};
?>

<div>

    <section class="relative py-16 transition-colors duration-700">

        <div class="container">

            <div class="mb-12">

                {{-- Breadcrumb --}}
                <nav
                    class="flex items-center gap-2 text-[10px] font-black text-gray-400 mb-6
               bg-white/30 dark:bg-white/[0.02] w-fit px-4 py-2 rounded-full
               border border-white/40 dark:border-white/5 backdrop-blur-md"
                    dir="rtl"
                >

                    <a href="{{ route('home') }}"
                       class="hover:text-brown-500 transition-colors">
                        خانه
                    </a>

                    @if($category->parent)
                        <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 "
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path d="M15 19l-7-7 7-7"
                                  stroke-width="3"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"/>
                        </svg>

                        <a href="{{ route('categories.show', $category->parent->slug) }}"
                           class="hover:text-brown-500 transition-colors">
                            {{ $category->parent->title }}
                        </a>
                    @endif

                    <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 "
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                        <path d="M15 19l-7-7 7-7"
                              stroke-width="3"
                              stroke-linecap="round"
                              stroke-linejoin="round"/>
                    </svg>

                    <span class="text-brown-600 dark:text-brown-400">
            {{ $category->title }}
        </span>

                </nav>


                {{-- Title + Sort --}}
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-8">

                    {{-- Title --}}
                    <div class="relative">

                        <div class="absolute -right-4 top-0 w-1 h-12
                        bg-brown-500 rounded-full blur-[2px]"></div>

                        <h1 class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">
                            {{ $category->title }}
                        </h1>

                        <div class="flex items-center gap-2 mt-3">

                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>

                            <p class="text-xs text-gray-500 dark:text-gray-400 font-bold">
                                <span class="text-gray-800 dark:text-white">
                        {{ number_format($this->paginator->total()) }}
                    </span>
                                محصول
                                @if($this->activeFilters)<span class="text-brown-500">با فیلترهای انتخاب‌شده</span>@endif
                            </p>

                        </div>

                    </div>


                    {{-- Sort --}}
                    <div
                        class="flex items-center gap-1 bg-white/40 dark:bg-white/[0.03]
                   backdrop-blur-md border border-white/40 dark:border-white/10
                   p-2 rounded-[1.8rem] shadow-lg shadow-gray-200/40
                   dark:shadow-none"
                    >

                        <div class="flex items-center px-4 gap-2">

                            <svg class="w-4 h-4 text-brown-500"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"
                                />
                            </svg>

                            <span class="text-[10px] font-black text-gray-400 whitespace-nowrap">
                    مرتب‌سازی:
                </span>

                        </div>


                        <div class="flex items-center gap-1 overflow-x-auto scrollbar-hide">
                            @foreach($this::SORTS as $sortKey => $sortLabel)
                                <button
                                    wire:click="setSort('{{ $sortKey }}')"
                                    class="px-4 py-2.5 rounded-[1.2rem] text-[11px] font-black whitespace-nowrap
                               transition-all active:scale-95
                               {{ $sort === $sortKey
                                    ? 'bg-brown-500 text-white shadow-lg shadow-brown-500/25'
                                    : 'text-gray-500 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-white/5'
                               }}"
                                >
                                    {{ $sortLabel }}
                                </button>
                            @endforeach
                        </div>

                    </div>

                </div>

            </div>

            <!-- Filter Showing in Responsive Break Point -->
            <div class="fixed bottom-28 right-6 z-[95] lg:hidden">
                <button onclick="toggleFilters(true)" aria-label="فیلترها" class="relative flex items-center justify-center w-14 h-14 bg-white/40 dark:bg-white/[0.05] backdrop-blur-md text-brown-600 rounded-2xl shadow-lg border border-white/60 dark:border-white/10 active:scale-90 transition-all">
                    @if(count($this->activeFilters))
                        <span class="absolute -top-1.5 -left-1.5 min-w-5 h-5 px-1 rounded-full bg-brown-600 text-white text-[10px] font-black flex items-center justify-center">{{ count($this->activeFilters) }}</span>
                    @endif
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                    </svg>
                </button>
            </div>

            <div id="filter-overlay" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-[140] opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>

            {{-- Offcanvas + Desktop aside هر دو از یک partial فیلترها استفاده می‌کنند تا کد تکراری نشود --}}
            <div id="filter-offcanvas" class="fixed top-0 right-0 h-full w-[85%] max-w-[380px] bg-white/30 dark:bg-black/40 backdrop-blur-[30px] z-[150] translate-x-full transition-transform duration-500 ease-in-out border-l border-white/40 dark:border-white/10 shadow-lg lg:hidden">
                <div class="flex flex-col h-full">
                    <div class="p-6 flex items-center justify-between border-b border-white/40 dark:border-white/5 bg-white/20 dark:bg-white/[0.02]">
                        <h2 class="text-lg font-black text-gray-900 dark:text-white">فیلترها</h2>
                        <button onclick="toggleFilters(false)" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white/40 dark:bg-white/5 text-gray-600 dark:text-gray-300 border border-white/60 dark:border-white/10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-8 custom-scrollbar">
                        @include('components.main.sections.category-filters')
                    </div>

                    <div class="p-6 bg-white/30 dark:bg-black/20 border-t border-white/40 dark:border-white/5">
                        <button onclick="toggleFilters(false)" class="w-full bg-brown-600 text-white py-4 rounded-[1.8rem] font-black shadow-lg shadow-brown-600/30 active:scale-95 transition-all">نمایش {{ number_format($this->paginator->total()) }} محصول</button>
                    </div>
                </div>
            </div>


            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

                <aside class="hidden lg:block lg:col-span-1 relative">
                    <div class="sticky top-10 space-y-6">
                        @include('components.main.sections.category-filters')
                    </div>
                </aside>

                <div class="lg:col-span-3">
                    {{-- فیلترهای فعال --}}
                    @if($this->activeFilters)
                        <div class="flex flex-wrap items-center gap-2 mb-6" dir="rtl">
                            @foreach($this->activeFilters as $chip)
                                <button type="button"
                                        wire:click="removeFilter('{{ $chip['type'] }}', @js($chip['key']), @js($chip['value']))"
                                        wire:key="chip-{{ $loop->index }}-{{ md5(json_encode($chip)) }}"
                                        class="group/chip inline-flex items-center gap-2 pl-2 pr-3 py-2 rounded-xl bg-brown-500/10 border border-brown-500/20 text-[11px] font-black text-brown-700 dark:text-brown-300 hover:bg-red-500/10 hover:border-red-500/30 hover:text-red-600 transition-all">
                                    {{ $chip['label'] }}
                                    <svg class="w-3.5 h-3.5 opacity-60 group-hover/chip:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endforeach
                            <button type="button" wire:click="clearFilters"
                                    class="px-3 py-2 rounded-xl text-[11px] font-black text-gray-500 dark:text-gray-400 hover:text-red-500 hover:bg-red-500/10 transition-all">
                                حذف همه فیلترها
                            </button>
                        </div>
                    @endif

                    <div class="relative">
                    <div wire:loading.delay class="absolute inset-0 z-30 rounded-[2rem] bg-white/40 dark:bg-black/30 backdrop-blur-[2px]"></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">

                        @forelse($this->viewProducts as $row)

                            @php
                                $product = $row->product;
                                $variant = $row->variant;
                            @endphp

                            @if($row->image_style === 'transparent')

                                {{-- ===================== Card Type 1 : Transparent ===================== --}}
                                <div class="group relative h-full pt-12">

                                    <div
                                        class="absolute inset-0
                               bg-white/80 dark:bg-[#0a0a0a]/40
                               backdrop-blur-[20px]
                               rounded-[3rem]
                               border border-gray-100 dark:border-white/[0.08]
                               shadow-[0_20px_50px_rgba(0,0,0,0.02)]
                               transition-all duration-700
                               group-hover:border-brown-500/50
                               dark:group-hover:shadow-[0_0_60px_rgba(120,72,45,0.12)]"
                                    ></div>


                                    <div
                                        class="relative p-7 flex flex-col h-full z-10
                               transition-transform duration-500
                               group-hover:-translate-y-4"
                                    >

                                        {{-- Discount --}}
                                        @if($row->discount_percent > 0)

                                            <div class="absolute -top-6 -right-2 z-20">

                                                <div
                                                    class="bg-secondary-500 dark:bg-[#ff1744]
                                       text-white text-[12px] font-black
                                       w-12 h-12 rounded-[1.2rem]
                                       flex items-center justify-center
                                       shadow-lg shadow-red-500/40
                                       dark:shadow-[#ff1744]/30
                                       rotate-12
                                       group-hover:rotate-0
                                       transition-all duration-500
                                       border-2 border-white dark:border-white/20"
                                                >
                                                    {{ $row->discount_percent }}٪
                                                </div>

                                            </div>

                                        @endif


                                        {{-- Image --}}
                                        <div class="relative mb-8 flex items-center justify-center min-h-[180px]">

                                            <div
                                                class="absolute w-40 h-40
                                   bg-brown-500/20 dark:bg-brown-500/20
                                   blur-[70px] rounded-full
                                   opacity-0 group-hover:opacity-100
                                   transition-all duration-1000"
                                            ></div>


                                            @if($row->image)

                                                <a href="{{ route('products.show', $product->slug) }}">
                                                    <img
                                                        src="{{ asset('storage/' . $row->image->file_path) }}"
                                                        class="relative z-10 w-full h-44 object-contain
                                           transition-all duration-700
                                           group-hover:scale-110
                                           group-hover:drop-shadow-[0_15px_35px_rgba(120,72,45,0.3)]"
                                                        alt="{{ $product->title }}"
                                                    >
                                                </a>

                                            @else

                                                <div
                                                    class="relative z-10 w-full h-44
                                       flex items-center justify-center
                                       text-gray-300 dark:text-zinc-700"
                                                >
                                                    <svg class="w-20 h-20"
                                                         fill="none"
                                                         stroke="currentColor"
                                                         viewBox="0 0 24 24">
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M4 16l4-4 4 4 4-5 4 5M4 19h16M5 5h14a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"
                                                        />
                                                    </svg>
                                                </div>

                                            @endif


                                            {{-- Actions --}}
                                            <div
                                                class="absolute top-0 -left-2 z-20
                                   flex flex-col gap-3
                                   opacity-0 group-hover:opacity-100
                                   -translate-x-4 group-hover:translate-x-0
                                   transition-all duration-500"
                                            >

                                                {{-- Quick View --}}
                                                <div class="relative flex items-center group/tooltip">

                                                    <a
                                                        href="{{ route('products.show', $product->slug) }}"
                                                        x-data x-on:click.prevent="$dispatch('zc-quick-view', { id: {{ $product->id }} })"
                                                        class="w-10 h-10
                                           bg-white/90 dark:bg-zinc-900/90
                                           backdrop-blur-md
                                           text-gray-900 dark:text-white
                                           rounded-xl flex items-center justify-center
                                           shadow-sm border border-white dark:border-white/10
                                           hover:bg-secondary-500
                                           hover:text-white transition-all"
                                                    >

                                                        <svg class="w-5 h-5"
                                                             fill="none"
                                                             stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                            />
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M2.458 12C3.732 7.943 7.523 5 12 5
                                               c4.478 0 8.268 2.943 9.542 7
                                               -1.274 4.057-5.064 7-9.542 7
                                               -4.477 0-8.268-2.943-9.542-7z"
                                                            />
                                                        </svg>

                                                    </a>

                                                    <span
                                                        class="absolute right-full mr-3 whitespace-nowrap
                                           bg-gray-900 dark:bg-zinc-800 text-white
                                           text-[10px] py-1.5 px-3 rounded-lg
                                           opacity-0 pointer-events-none
                                           translate-x-2
                                           group-hover/tooltip:opacity-100
                                           group-hover/tooltip:translate-x-0
                                           transition-all duration-300
                                           border border-white/5"
                                                    >
                                    مشاهده سریع
                                </span>

                                                </div>


                                                {{-- Favorite --}}
                                                <div class="relative flex items-center group/tooltip">

                                                    <button
                                                        type="button"
                                                        wire:click="toggleFavorite({{ $product->id }})"
                                                        class="w-10 h-10
                                           bg-white/90 dark:bg-zinc-900/90
                                           backdrop-blur-md
                                           rounded-xl flex items-center justify-center
                                           shadow-sm border border-white dark:border-white/10
                                           transition-all
                                           {{ $row->is_favorited ? 'text-red-500' : 'text-gray-900 dark:text-white hover:text-red-500' }}"
                                                    >

                                                        <svg class="w-5 h-5"
                                                             fill="{{ $row->is_favorited ? 'currentColor' : 'none' }}"
                                                             stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M4.318 6.318a4.5 4.5 0 000 6.364
                                               L12 20.364l7.682-7.682
                                               a4.5 4.5 0 00-6.364-6.364
                                               L12 7.636l-1.318-1.318
                                               a4.5 4.5 0 00-6.364 0z"
                                                            />
                                                        </svg>

                                                    </button>

                                                    <span
                                                        class="absolute right-full mr-3 whitespace-nowrap
                                           bg-gray-900 dark:bg-zinc-800 text-white
                                           text-[10px] py-1.5 px-3 rounded-lg
                                           opacity-0 pointer-events-none
                                           translate-x-2
                                           group-hover/tooltip:opacity-100
                                           group-hover/tooltip:translate-x-0
                                           transition-all duration-300
                                           border border-white/5"
                                                    >
                                    {{ $row->is_favorited ? 'حذف از علاقه‌مندی' : 'افزودن به علاقه‌مندی' }}
                                </span>

                                                </div>

                                            </div>

                                        </div>


                                        {{-- Title --}}
                                        <a href="{{ route('products.show', $product->slug) }}">
                                            <h3
                                                class="text-[15px] font-black
                                                       text-gray-800 dark:text-zinc-100
                                                       mb-6 line-clamp-2 leading-7 h-14
                                                       group-hover:text-brown-600
                                                       dark:group-hover:text-brown-400
                                                       transition-colors"
                                            >
                                                {{ $product->title }}
                                            </h3>
                                        </a>


                                        {{-- Price --}}
                                        <div
                                            class="flex items-center justify-between
                               mt-auto pt-5
                               border-t border-gray-100 dark:border-white/5"
                                        >

                                            <div class="flex flex-col gap-1">

                                                @if($row->discount_percent > 0)

                                                    <span
                                                        class="text-[11px] text-gray-400 dark:text-zinc-500
                                           line-through tabular-nums leading-none"
                                                    >
                                    {{ number_format($row->compare_price && $row->compare_price > $row->final_price ? $row->compare_price : $row->price) }}
                                </span>

                                                @endif


                                                <div class="flex items-center gap-1.5">

                                <span
                                    class="text-2xl font-black
                                           text-gray-900 dark:text-white
                                           tracking-tighter tabular-nums"
                                >
                                    {{ number_format($row->final_price) }}
                                </span>

                                                    <span
                                                        class="text-[10px] text-gray-400
                                           dark:text-zinc-500 font-bold"
                                                    >
                                    تومان
                                </span>

                                                </div>

                                            </div>


                                            {{-- Add To Cart --}}
                                            <a
                                                href="{{ route('products.show', $product->slug) }}"
                                                class="w-10 h-10
               rounded-xl
               bg-gray-100 dark:bg-white/5
               text-gray-500 dark:text-gray-300
               flex items-center justify-center
               hover:bg-brown-500 hover:text-white
               hover:scale-110
               transition-all"
                                                title="مشاهده محصول"
                                            >
                                                <svg
                                                    class="w-5 h-5 rtl:rotate-180"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 5l7 7-7 7"
                                                    />
                                                </svg>
                                            </a>

                                        </div>

                                    </div>

                                </div>

                            @else

                                {{-- ===================== Card Type 2 : Background ===================== --}}
                                <div class="group relative h-full">
                                    <div
                                        class="relative h-full flex flex-col
                                       rounded-[2rem]
                                       overflow-hidden
                                       border border-gray-100 dark:border-white/[0.08]
                                       shadow-[0_20px_50px_rgba(0,0,0,0.06)] dark:shadow-none
                                       bg-white dark:bg-[#0a0a0a]/40
                                       transition-all duration-500
                                       group-hover:-translate-y-2
                                       group-hover:shadow-xl
                                       group-hover:border-brown-400/40
                                       dark:group-hover:shadow-[0_0_50px_rgba(120,80,60,0.15)]"
                                    >
                                        {{-- Image + Gradient --}}
                                        <div class="relative w-full h-56 sm:h-60 shrink-0 overflow-hidden">

                                            @if($row->image)
                                                <a href="{{ route('products.show', $product->slug) }}">
                                                    <img
                                                        src="{{ asset('storage/' . $row->image->file_path) }}"
                                                        class="absolute inset-0 w-full h-full object-cover
                                               transition-transform duration-700
                                               group-hover:scale-110"
                                                        alt="{{ $product->title }}"
                                                    >
                                                </a>
                                            @else
                                                <div class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-white/5 text-gray-300 dark:text-zinc-700">
                                                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4-4 4 4 4-5 4 5M4 19h16M5 5h14a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/>
                                                    </svg>
                                                </div>
                                            @endif

                                            {{-- Gradient قهوه‌ای برای خوانایی عنوان روی تصویر، هماهنگ با دارک/لایت --}}
                                            <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-brown-900/95 via-brown-800/55 to-transparent dark:from-brown-950/95 dark:via-brown-900/55 pointer-events-none"></div>

                                            {{-- Discount badge --}}
                                            @if($row->discount_percent > 0)
                                                <div class="absolute top-4 right-4 z-20 bg-secondary-500 dark:bg-[#ff1744] text-white text-[11px] font-black w-11 h-11 rounded-2xl flex items-center justify-center shadow-lg border-2 border-white/80 dark:border-white/20">
                                                    {{ $row->discount_percent }}٪
                                                </div>
                                            @endif

                                            {{-- Actions --}}
                                            <div class="absolute top-4 left-4 z-20 flex flex-col gap-2 opacity-0 group-hover:opacity-100 -translate-y-2 group-hover:translate-y-0 transition-all duration-500">

                                                <div class="relative flex items-center group/tooltip">
                                                    <a
                                                        href="{{ route('products.show', $product->slug) }}"
                                                        x-data x-on:click.prevent="$dispatch('zc-quick-view', { id: {{ $product->id }} })"
                                                        class="w-9 h-9 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md text-gray-900 dark:text-white rounded-xl flex items-center justify-center shadow-sm hover:bg-secondary-500 hover:text-white transition-all"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </a>
                                                    <span class="absolute right-full mr-3 whitespace-nowrap bg-gray-900 dark:bg-zinc-800 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 pointer-events-none translate-x-2 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-x-0 transition-all duration-300 border border-white/5">
                                                        مشاهده سریع
                                                    </span>
                                                </div>

                                                <div class="relative flex items-center group/tooltip">
                                                    <button
                                                        type="button"
                                                        wire:click="toggleFavorite({{ $product->id }})"
                                                        class="w-9 h-9 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md rounded-xl flex items-center justify-center shadow-sm transition-all {{ $row->is_favorited ? 'text-red-500' : 'text-gray-900 dark:text-white hover:text-red-500' }}"
                                                    >
                                                        <svg class="w-4 h-4" fill="{{ $row->is_favorited ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                                        </svg>
                                                    </button>
                                                    <span class="absolute right-full mr-3 whitespace-nowrap bg-gray-900 dark:bg-zinc-800 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 pointer-events-none translate-x-2 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-x-0 transition-all duration-300 border border-white/5">
                                                        {{ $row->is_favorited ? 'حذف از علاقه‌مندی' : 'افزودن به علاقه‌مندی' }}
                                                    </span>
                                                </div>
                                            </div>

                                            {{-- Title روی گرادینت --}}
                                            <div class="absolute inset-x-0 bottom-0 p-4 z-10">
                                                <a href="{{ route('products.show', $product->slug) }}">
                                                    <h3 class="text-sm font-black text-white mb-0 line-clamp-2 leading-6 drop-shadow-[0_2px_4px_rgba(0,0,0,0.4)]">
                                                        {{ $product->title }}
                                                    </h3>
                                                </a>
                                            </div>
                                        </div>

                                        {{-- Price --}}
                                        <div class="p-5 mt-auto flex items-center justify-between border-t border-gray-100 dark:border-white/5">
                                            <div class="flex flex-col gap-1">
                                                @if($row->discount_percent > 0)
                                                    <span class="text-[11px] text-gray-400 dark:text-zinc-500 line-through tabular-nums leading-none">
                                                        {{ number_format($row->compare_price && $row->compare_price > $row->final_price ? $row->compare_price : $row->price) }}
                                                    </span>
                                                @endif
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-xl font-black text-gray-900 dark:text-white tracking-tighter tabular-nums">{{ number_format($row->final_price) }}</span>
                                                    <span class="text-[10px] text-gray-400 dark:text-zinc-500 font-bold">تومان</span>
                                                </div>
                                            </div>

                                            <a
                                                href="{{ route('products.show', $product->slug) }}"
                                                class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-gray-300 flex items-center justify-center hover:bg-brown-500 hover:text-white hover:scale-110 transition-all"
                                                title="مشاهده محصول"
                                            >
                                                <svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            @endif

                        @empty

                            <div class="col-span-full py-20 text-center">

                                <div
                                    class="w-20 h-20 mx-auto mb-5
                       rounded-3xl
                       bg-gray-100 dark:bg-white/5
                       flex items-center justify-center"
                                >
                                    <svg class="w-10 h-10 text-gray-300"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.5"
                                            d="M9.172 16.172a4 4 0 015.656 0
                           M9 10h.01M15 10h.01
                           M21 12a9 9 0 11-18 0
                           9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>

                                <h3 class="text-lg font-black text-gray-700 dark:text-gray-200">
                                    محصولی پیدا نشد
                                </h3>

                                <p class="text-xs text-gray-400 mt-2">
                                    در این دسته‌بندی محصولی با شرایط انتخاب‌شده وجود ندارد.
                                </p>
                                @if($this->activeFilters)
                                    <button type="button" wire:click="clearFilters" class="mt-5 px-6 py-3 rounded-2xl bg-brown-500 text-white text-xs font-black">حذف همه فیلترها</button>
                                @endif

                            </div>

                        @endforelse

                    </div>

                    </div>

                    {{-- صفحه‌بندی (فیلترها و مرتب‌سازی حفظ می‌شوند) --}}
                    <div class="mt-16 flex items-center justify-center">
                        {{ $this->paginator->onEachSide(1)->links() }}
                    </div>

                </div>
            </div>


        </div>

    </section>

</div>
