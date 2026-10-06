<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\Brand;

new class extends Component
{
    use WithPagination;
    use \App\Traits\HandlesWishlist;
    use \App\Traits\QueriesSectionProducts;

    protected $paginationTheme = 'tailwind';

    public Brand $brand;
    public string $sort = 'latest';

    public function mount($slug)
    {
        $this->brand = Brand::active()
            ->with(['logo', 'bannerImage'])
            ->where('slug', $slug)
            ->firstOrFail();

        // وضعیت علاقه‌مندی کاربر برای محصولات همین برند
        $this->loadWishlistIds(
            $this->brand->activeProducts()->pluck('products.id')
        );
    }

    // فقط محصولات فعال همین برند
    protected function scopeSectionProducts($query)
    {
        return $query
            ->active()
            ->whereNotNull('products.brand_id')
            ->where('products.brand_id', $this->brand->id);
    }

    public function setSort(string $sort): void
    {
        if (! array_key_exists($sort, $this->sorts())) {
            return;
        }

        $this->sort = $sort;
        $this->resetPage();
    }

    public function sorts(): array
    {
        return [
            'latest' => 'جدیدترین',
            'views' => 'پربازدیدترین',
        ];
    }

    #[Computed]
    public function productsCount(): int
    {
        return $this->brand->activeProducts()->count();
    }

    #[Computed]
    public function products()
    {
        return $this->sectionProductsQuery()
            ->when($this->sort === 'views', fn ($q) => $q->withCount('views')->orderByDesc('views_count'))
            ->latest('products.id')
            ->paginate(12);
    }
};
?>

<div>
    <section class="relative py-16 transition-colors duration-700" dir="rtl">

        <div class="container">

            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-2 text-[10px] font-black text-gray-400 mb-6 bg-white/30 dark:bg-white/[0.02] w-fit px-4 py-2 rounded-full border border-white/40 dark:border-white/5 backdrop-blur-md">
                <a href="{{ route('home') }}" class="hover:text-brown-500 transition-colors">
                    خانه
                </a>

                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M15 19l-7-7 7-7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>

                <a href="{{ route('brands.list') }}" class="hover:text-brown-500 transition-colors">
                    برندها
                </a>

                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M15 19l-7-7 7-7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>

                <span class="text-brown-600 dark:text-brown-400">
                    {{ $brand->title }}
                </span>
            </nav>

            {{-- Brand header --}}
            <div class="relative overflow-hidden bg-white/40 dark:bg-zinc-900/40 backdrop-blur-md border-2 border-gray-200 dark:border-white/10 rounded-[3rem] p-6 lg:p-10 mb-12 shadow-lg shadow-gray-200/50 dark:shadow-none">

                @if($brand->banner_image_url)
                    <img src="{{ $brand->banner_image_url }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-10 dark:opacity-5 pointer-events-none">
                @endif
                <div class="absolute -top-24 -right-24 w-64 h-64 bg-brown-600/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center gap-8">

                    {{-- Logo --}}
                    <div class="w-32 h-32 lg:w-40 lg:h-40 flex-shrink-0 rounded-[2.5rem] bg-white/80 dark:bg-white/5 border border-white dark:border-white/10 shadow-lg flex items-center justify-center p-5">
                        @if($brand->logo_url)
                            <img src="{{ $brand->logo_url }}" alt="{{ $brand->title }}" class="max-w-full max-h-full object-contain drop-shadow-md">
                        @else
                            <span class="text-4xl font-black text-brown-600">{{ mb_substr($brand->title, 0, 1) }}</span>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="w-2 h-8 bg-brown-600 rounded-full"></span>
                            <h1 class="text-3xl lg:text-4xl font-black text-gray-900 dark:text-white">{{ $brand->title }}</h1>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-[11px] font-black">
                            <span class="px-3 py-1.5 rounded-lg bg-brown-600/10 text-brown-600 dark:text-brown-400 border border-brown-600/20 tabular-nums">
                                {{ number_format($this->productsCount) }} محصول
                            </span>

                            @if($brand->country)
                                <span class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-gray-300">
                                    کشور: {{ $brand->country }}
                                </span>
                            @endif

                            @if($brand->website && preg_match('#^https?://#i', $brand->website))
                                <a href="{{ $brand->website }}" target="_blank" rel="nofollow noopener" class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-gray-300 hover:text-brown-600 transition-colors" dir="ltr">
                                    {{ parse_url($brand->website, PHP_URL_HOST) ?: $brand->website }}
                                </a>
                            @endif
                        </div>

                        @if(filled($brand->description))
                            <div class="text-[14px] font-medium text-gray-600 dark:text-gray-300 leading-8 max-w-3xl">
                                {!! nl2br(e(strip_tags($brand->description))) !!}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Products header + sort --}}
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6 border-r-4 border-brown-600 pr-2 pl-2">
                <div>
                    <h2 class="text-2xl lg:text-3xl font-black text-gray-900 dark:text-white">
                        محصولات <span class="text-brown-600">{{ $brand->title }}</span>
                    </h2>
                </div>

                <div class="flex items-center gap-2 p-1.5 bg-white/60 dark:bg-white/5 backdrop-blur-md rounded-2xl border border-white dark:border-white/10 shadow-sm overflow-x-auto no-scrollbar">
                    @foreach($this->sorts() as $key => $label)
                        <button
                            type="button"
                            wire:click="setSort('{{ $key }}')"
                            class="px-6 py-2.5 rounded-xl font-black text-xs whitespace-nowrap transition-all duration-300 {{ $sort === $key ? 'bg-brown-600 text-white shadow-lg shadow-brown-600/20' : 'text-gray-500 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/10' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Products grid --}}
            <div class="relative">
                <div wire:loading.delay.class="opacity-50 pointer-events-none" wire:target="setSort, gotoPage, nextPage, previousPage"
                     class="grid pb-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8 transition-opacity duration-300">
                    @forelse($this->products as $product)
                        <x-main.products.card
                            wire:key="brand-product-{{ $product->id }}"
                            :product="$product"
                            :wishlisted="$this->isWishlisted($product->id)"
                        />
                    @empty
                        <div class="col-span-full text-center py-20">
                            <h3 class="text-lg font-black text-gray-700 dark:text-gray-200">
                                محصولی پیدا نشد
                            </h3>
                            <p class="text-xs text-gray-400 mt-2">
                                هنوز محصولی برای این برند ثبت نشده است.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="mt-16 flex items-center justify-center">
                {{ $this->products->onEachSide(1)->links() }}
            </div>

        </div>
    </section>
</div>
