<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Services\Search\GlobalSearchService;

new class extends Component
{
    use WithPagination;
    use \App\Traits\HandlesWishlist;

    protected $paginationTheme = 'tailwind';

    #[Url(except: '')]
    public string $q = '';

    // all | products | brands | categories | tags | articles | pages
    #[Url(except: 'all')]
    public string $type = 'all';

    // فیلتر محتوای دارای یک تگ (لینک نتایج تگ)
    #[Url(except: '')]
    public string $tag = '';

    public function mount()
    {
        // سازگاری با لینک‌های قدیمی ?search=
        if ($this->q === '' && filled(request('search'))) {
            $this->q = (string) request('search');
        }

        $this->q = $this->search()->normalize($this->q);

        if ($this->type !== 'all' && ! $this->search()->isValidType($this->type)) {
            $this->type = 'all';
        }
    }

    protected function search(): GlobalSearchService
    {
        return app(GlobalSearchService::class);
    }

    public function updatedQ(): void
    {
        $this->q = $this->search()->normalize($this->q);
        $this->resetPage();
    }

    public function setType(string $type): void
    {
        $this->type = ($type === 'all' || $this->search()->isValidType($type)) ? $type : 'all';
        $this->resetPage();
    }

    public function clearTag(): void
    {
        $this->tag = '';
        $this->type = 'all';
        $this->resetPage();
    }

    #[Computed]
    public function activeTag()
    {
        return $this->search()->findTag($this->tag ?: null);
    }

    // اسلاگ تگ فقط وقتی معتبر و فعال باشد اعمال می‌شود
    protected function tagSlug(): ?string
    {
        return $this->activeTag?->slug;
    }

    #[Computed]
    public function hasQuery(): bool
    {
        return $this->search()->isSearchable($this->q) || $this->tagSlug();
    }

    #[Computed]
    public function counts(): array
    {
        return $this->hasQuery ? $this->search()->counts($this->q, $this->tagSlug()) : [];
    }

    #[Computed]
    public function total(): int
    {
        return array_sum($this->counts);
    }

    /**
     * تب «همه»: چند نتیجه‌ی اول هر نوع
     */
    #[Computed]
    public function overview(): array
    {
        $groups = [];

        foreach ($this->counts as $type => $count) {
            if (! $count) {
                continue;
            }

            $models = in_array($type, ['products', 'brands'], true);
            $items = $this->search()->take($type, $this->q, $type === 'products' ? 6 : 6, $this->tagSlug(), ! $models);

            if ($type === 'products') {
                $items->loadMissing($this->productCardRelations());
            }

            $groups[$type] = [
                'label' => GlobalSearchService::TYPES[$type],
                'count' => $count,
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * تب یک نوع مشخص: نتایج صفحه‌بندی‌شده
     */
    #[Computed]
    public function results()
    {
        if (! $this->hasQuery || $this->type === 'all' || ! array_key_exists($this->type, $this->counts)) {
            return null;
        }

        $models = in_array($this->type, ['products', 'brands'], true);
        $paginator = $this->search()->paginate($this->type, $this->q, 12, $this->tagSlug(), 'page', ! $models);

        if ($this->type === 'products') {
            $paginator->getCollection()->loadMissing($this->productCardRelations());
        }

        return $paginator;
    }

    protected function productCardRelations(): array
    {
        return [
            'media',
            'specifications' => fn ($q) => $q->where('product_specifications.status', true),
        ];
    }

    // وضعیت علاقه‌مندی محصولاتِ همین صفحه
    public function rendering(): void
    {
        $products = $this->type === 'products'
            ? $this->results?->getCollection()
            : ($this->type === 'all' ? ($this->overview['products']['items'] ?? collect()) : collect());

        $this->loadWishlistIds(collect($products)->pluck('id'));
    }
};
?>

<div>
    <section class="relative py-16 transition-colors duration-700" dir="rtl">

        <div class="container">

            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-2 text-[10px] font-black text-gray-400 mb-6 bg-white/30 dark:bg-white/[0.02] w-fit px-4 py-2 rounded-full border border-white/40 dark:border-white/5 backdrop-blur-md">
                <a href="{{ route('home') }}" class="hover:text-brown-500 transition-colors">خانه</a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M15 19l-7-7 7-7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="text-brown-600 dark:text-brown-400">جستجو</span>
            </nav>

            {{-- Header + search box --}}
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-8 border-r-4 border-brown-600 pr-2 pl-2">
                <div>
                    <h1 class="text-3xl lg:text-4xl font-black text-gray-900 dark:text-white">
                        @if($this->activeTag)
                            محتوای تگ <span class="text-brown-600">#{{ $this->activeTag->title }}</span>
                        @elseif($this->hasQuery)
                            نتایج جستجو برای <span class="text-brown-600">«{{ $q }}»</span>
                        @else
                            <span class="text-brown-600">جستجو</span> در فروشگاه
                        @endif
                    </h1>
                    @if($this->hasQuery)
                        <p class="text-gray-500 dark:text-gray-400 mt-2 font-bold text-sm">
                            {{ number_format($this->total) }} نتیجه پیدا شد
                        </p>
                    @endif
                </div>

                <div class="relative w-full lg:max-w-md">
                    <input type="search"
                           wire:model.live.debounce.500ms="q"
                           autocomplete="off"
                           maxlength="{{ \App\Services\Search\GlobalSearchService::MAX_LENGTH }}"
                           class="w-full bg-white/70 dark:bg-white/[0.03] backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl py-4 pr-12 pl-4 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-4 ring-brown-600/10 focus:border-brown-600 transition-all placeholder:text-gray-400 shadow-sm"
                           placeholder="عبارت موردنظر را جستجو کنید...">
                    <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-400">
                        <svg wire:loading.remove wire:target="q" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                        <span wire:loading wire:target="q" class="w-5 h-5 rounded-full border-2 border-brown-600/20 border-t-brown-600 animate-spin"></span>
                    </div>
                </div>
            </div>

            @if($this->activeTag)
                <div class="mb-6">
                    <button type="button" wire:click="clearTag" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brown-600/10 text-brown-600 text-[11px] font-black border border-brown-600/20 hover:bg-brown-600 hover:text-white transition-all">
                        #{{ $this->activeTag->title }}
                        <span class="text-base leading-none">×</span>
                    </button>
                </div>
            @endif

            @if(! $this->hasQuery)
                {{-- Initial state --}}
                <div class="text-center py-20">
                    <span class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-brown-600/10 text-brown-600 flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                    </span>
                    <h3 class="text-lg font-black text-gray-700 dark:text-gray-200">چه چیزی را جستجو می‌کنید؟</h3>
                    <p class="text-xs text-gray-400 mt-2">
                        حداقل {{ \App\Services\Search\GlobalSearchService::MIN_LENGTH }} حرف از نام محصول، برند، دسته‌بندی، تگ، مقاله یا صفحه را وارد کنید.
                    </p>
                </div>
            @else

                {{-- Type filter tabs --}}
                <div class="flex items-center gap-2 p-1.5 mb-10 bg-white/60 dark:bg-white/5 backdrop-blur-md rounded-2xl border border-white dark:border-white/10 shadow-sm overflow-x-auto no-scrollbar w-fit max-w-full">
                    @php($tabs = ['all' => 'همه'] + array_intersect_key(\App\Services\Search\GlobalSearchService::TYPES, $this->counts))
                    @foreach($tabs as $key => $label)
                        @php($count = $key === 'all' ? $this->total : ($this->counts[$key] ?? 0))
                        <button
                            type="button"
                            wire:key="search-tab-{{ $key }}"
                            wire:click="setType('{{ $key }}')"
                            @disabled($key !== 'all' && ! $count)
                            class="flex-shrink-0 flex items-center gap-2 px-5 py-2.5 rounded-xl font-black text-xs whitespace-nowrap transition-all duration-300 disabled:opacity-40 disabled:cursor-not-allowed {{ $type === $key ? 'bg-brown-600 text-white shadow-lg shadow-brown-600/20' : 'text-gray-500 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/10' }}">
                            {{ $label }}
                            <span class="px-1.5 py-0.5 rounded-md text-[10px] tabular-nums {{ $type === $key ? 'bg-white/20' : 'bg-gray-100 dark:bg-white/10' }}">{{ number_format($count) }}</span>
                        </button>
                    @endforeach
                </div>

                <div wire:loading.class="opacity-50 pointer-events-none" wire:target="q, setType, clearTag, gotoPage, nextPage, previousPage" class="transition-opacity duration-300">

                    @if($this->total === 0)
                        {{-- Empty state --}}
                        <div class="text-center py-20">
                            <span class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-gray-100 dark:bg-white/5 text-gray-400 flex items-center justify-center">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <h3 class="text-lg font-black text-gray-700 dark:text-gray-200">نتیجه‌ای پیدا نشد</h3>
                            <p class="text-xs text-gray-400 mt-2">املای عبارت را بررسی کنید یا عبارت کوتاه‌تر و کلی‌تری را امتحان کنید.</p>
                        </div>

                    @elseif($type === 'all')
                        <div class="space-y-14">
                            @foreach($this->overview as $groupType => $group)
                                <div wire:key="search-overview-{{ $groupType }}">
                                    <div class="flex items-center justify-between gap-4 mb-6">
                                        <div class="flex items-center gap-3">
                                            <span class="w-2 h-7 bg-brown-600 rounded-full"></span>
                                            <h2 class="text-xl font-black text-gray-900 dark:text-white">{{ $group['label'] }}</h2>
                                            <span class="px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-white/10 text-[11px] font-black text-gray-500 dark:text-gray-300 tabular-nums">{{ number_format($group['count']) }}</span>
                                        </div>

                                        @if($group['count'] > count($group['items']))
                                            <button type="button" wire:click="setType('{{ $groupType }}')" class="text-[12px] font-black text-brown-600 hover:underline">
                                                مشاهده همه {{ $group['label'] }}
                                            </button>
                                        @endif
                                    </div>

                                    @include('pages.main.search.partials.items', ['itemsType' => $groupType, 'items' => $group['items']])
                                </div>
                            @endforeach
                        </div>

                    @elseif($this->results)
                        @include('pages.main.search.partials.items', ['itemsType' => $type, 'items' => $this->results])

                        <div class="mt-16 flex items-center justify-center">
                            {{ $this->results->onEachSide(1)->links() }}
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </section>
</div>
