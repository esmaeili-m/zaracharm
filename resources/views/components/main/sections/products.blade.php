<?php

use Livewire\Component;
use App\Models\Product;

new class extends Component
{
    public $products;
    public $data;
    public $view;
    public $categories = [];

    public function mount($data)
    {
        $this->data = $data;

        if (!$data) {
            return;
        }

        $this->loadProducts();

        $this->categories = $this->products
            ->pluck('categories')
            ->flatten()
            ->unique('id')
            ->values();

        $this->view = $this->resolveViewTitle((int) ($data['view'] ?? 1));
    }

    protected function resolveViewTitle(int $view): string
    {
        return match ($view) {
            1 => 'جدیدترین ',
            2 => 'پرفروش‌ترین ',
            3 => 'پربازدیدترین ',
            4 => 'پیشنهادی‌ترین ',
            5 => 'منتخب ',
            default => 'جدیدترین ',
        };
    }

    /**
     * کوئری پایه با تمام روابطی که در بلید (هر ۳ مود) استفاده می‌شوند
     * تا N+1 Query نداشته باشیم.
     */
    protected function baseQuery()
    {
        return Product::query()
            ->has('variants')
            ->with([
                'categories',
                'brand',
                'media',
                'cheapestVariant',
                'displayVariant',
                'specifications' => fn ($q) => $q->where('product_specifications.status', true),
            ]);
    }

    protected function getBestSellingProducts($limit)
    {
        $productIds = Product::query()
            ->has('variants')
            ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
            ->join('order_items', 'order_items.variant_id', '=', 'product_variants.id')
            ->select('products.id')
            ->selectRaw('SUM(order_items.quantity) as total_sales')
            ->groupBy('products.id')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->pluck('id');

        // حفظ ترتیب پرفروش‌ترین‌ها بعد از whereIn
        return $this->baseQuery()
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn ($product) => $productIds->search($product->id))
            ->values();
    }

    protected function loadProducts()
    {
        $limit = $this->data['limit'] ?? 8;

        $this->products = match ($this->data['mode'] ?? null) {

            'latest' => $this->baseQuery()
                ->latest()
                ->limit($limit)
                ->get(),

            'sales' => $this->getBestSellingProducts($limit),

            'views' => $this->baseQuery()
                ->withCount('views')
                ->orderByDesc('views_count')
                ->limit($limit)
                ->get(),

            'random' => $this->baseQuery()
                ->inRandomOrder()
                ->limit($limit)
                ->get(),

            'manual' => $this->baseQuery()
                ->whereIn('id', $this->data['product_ids'] ?? [])
                ->get(),

            default => collect(),
        };
    }
};
?>

<div>
    @if(($data['view'] ?? 1) == 1)
        <section class="relative transition-colors duration-500 overflow-hidden">

            <div class="container mx-auto relative z-10">

                <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6 border-r-4 border-brown-600 pr-6">
                    <div>
                        <h2 class="text-3xl lg:text-4xl font-black text-gray-900 dark:text-white">{{$view}} <span class="text-brown-600">محصولات</span></h2>
                        <p class="text-gray-500 dark:text-gray-400 mt-2 font-bold text-sm">برترین های روز دنیا در دستان شما</p>
                    </div>

                    <div class="flex items-center gap-2 p-1.5 bg-white/60 dark:bg-white/5 backdrop-blur-md rounded-2xl border border-white dark:border-white/10 shadow-sm overflow-x-auto no-scrollbar">

                        {{-- همه --}}
                        <button
                            data-filter="all"
                            class="shop-filter-btn active px-6 py-2.5 rounded-xl font-black text-xs transition-all duration-300">
                            همه
                        </button>

                        @foreach($categories as $category)
                            <button
                                data-filter="{{ $category->slug }}"
                                class="shop-filter-btn px-6 py-2.5 rounded-xl font-black text-xs text-gray-500 dark:text-gray-400 hover:bg-white/50 dark:hover:bg-white/10 transition-all duration-300">
                                {{ $category->title }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="product-grid" class="grid pb-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                    @foreach($products ?? [] as $product)
                        @php
                            $prices = $product->cheapestVariant?->priceData() ?? [
                                'price' => 0,
                                'after_discount' => 0,
                                'has_discount' => false,
                                'discount_percent' => 0,
                            ];
                        @endphp
                        <div
                            class="product-card group relative bg-white/70 dark:bg-white/[0.03] backdrop-blur-md rounded-[2.5rem] border border-gray-200 dark:border-white/10 p-2 transition-all duration-500 hover:shadow-lg hover:shadow-brown-600/20 hover:-translate-y-2"
                            data-categories="{{ $product->categories->pluck('slug')->implode(' ') }}"
                        >
                            <div class="flex h-[220px]">

                                {{-- Product Image --}}
                                <div class="w-2/5 relative rounded-[2rem] overflow-hidden m-1 transition-all duration-500 group-hover:scale-[0.98]">

                                    @if(($data['pictureMode'] ?? 'background') === 'background')

                                        <img
                                            src="{{ $product->featuredImageUrl }}"
                                            alt="{{ $product->title }}"
                                            class="absolute inset-0 w-full h-full object-cover scale-100 group-hover:scale-110 transition-transform duration-1000 ease-out"
                                        >

                                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-all duration-500"></div>

                                    @else

                                        {{-- Transparent / Product Mode --}}
                                        <div class="w-full h-full relative bg-gradient-to-br from-gray-100 to-transparent dark:from-white/5 dark:to-transparent flex items-center justify-center">

                                            <img
                                                src="{{ $product->featuredImageUrl }}"
                                                class="w-32 h-32 object-contain drop-shadow-md transition-transform duration-700 group-hover:scale-110 group-hover:-rotate-6"
                                                alt="{{ $product->title }}"
                                            >

                                        </div>

                                    @endif

                                    {{-- Discount --}}
                                    @if($prices['has_discount'])
                                        <div class="absolute top-3 right-3 bg-red-500 text-white text-[10px] font-black px-2.5 py-1 rounded-lg shadow-lg shadow-red-500/40">
                                            {{ $prices['discount_percent'] }}٪-
                                        </div>
                                    @endif

                                </div>
                                {{-- Product Info --}}
                                <div class="w-3/5 p-5 flex flex-col justify-between">

                                    <div>

                                        <div class="flex justify-between items-start">

                <span class="text-[10px] font-bold text-brown-600 dark:text-brown-400 tracking-tighter opacity-80 mb-1 block">
                    {{ $product->brand?->title ?? 'محصول' }}
                </span>

                                            <div class="flex gap-1">
                                                <div class="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_5px_rgba(59,130,246,0.5)]"></div>
                                                <div class="w-2 h-2 rounded-full bg-gray-800 shadow-[0_0_5px_rgba(0,0,0,0.5)]"></div>
                                            </div>

                                        </div>

                                        <h3 class="font-black text-gray-900 dark:text-white text-base leading-tight mb-2">
                                            {{ $product->title }}
                                        </h3>

                                        <div class="flex flex-wrap gap-1.5 mt-2">
                                            @foreach($product->specifications->take(2) as $specification)

                                                @php
                                                    $value = match ($specification->type) {
                                                        1 => $specification->pivot->text_value,
                                                        2 => $specification->pivot->number_value,
                                                        3 => $specification->pivot->decimal_value,
                                                        4 => $specification->pivot->boolean_value !== null
                                                            ? ($specification->pivot->boolean_value ? 'بله' : 'خیر')
                                                            : null,
                                                        5 => $specification->pivot->date_value,
                                                        default => null,
                                                    };
                                                @endphp

                                                @if($value !== null && $value !== '')
                                                    <div
                                                        class="inline-flex items-center gap-1
                                                       px-2 py-1
                                                       rounded-lg
                                                       bg-gray-100/80 dark:bg-white/[0.05]
                                                       border border-gray-200/70 dark:border-white/[0.08]
                                                       shadow-sm
                                                       text-[7px] font-bold
                                                       text-gray-500 dark:text-gray-400
                                                       transition-all duration-300
                                                       hover:-translate-y-0.5
                                                       hover:bg-brown-50 dark:hover:bg-brown-500/10
                                                       hover:border-brown-200 dark:hover:border-brown-500/30
                                                       hover:text-brown-600 dark:hover:text-brown-400
                                                       hover:shadow-md hover:shadow-brown-500/10"
                                                    >
            <span class="opacity-70">
                {{ $specification->title }}:
            </span>

                                                        <span class="font-black text-gray-700 dark:text-gray-200">
                {{ $value }}
            </span>
                                                    </div>
                                                @endif

                                            @endforeach
                                        </div>

                                    </div>

                                    <div class="mt-auto">

                                        <div class="mb-3 text-left">

                                            @if($prices['has_discount'])
                                                <p class="text-[10px] text-gray-400 line-through mb-0.5">
                                                    {{ number_format($prices['price'] ?? 0) }}
                                                </p>
                                            @endif

                                            <div class="flex items-baseline justify-end gap-1">

                    <span class="text-xl font-black text-gray-900 dark:text-white tracking-tighter">
                        {{ number_format($prices['after_discount'] ?? 0) }}
                    </span>

                                                <span class="text-[10px] font-bold text-gray-500">
                        تومان
                    </span>

                                            </div>

                                        </div>


                                       <a href="{{ route('products.show', $product->slug) }}"
                                        class="w-full py-3 bg-brown-500 text-white rounded-xl text-[11px] font-black shadow-lg shadow-brown-500/20 hover:bg-brown-700 transition-all flex items-center justify-center gap-2 group/btn"
                                        >
                                        <span>خرید سریع</span>

                                        <svg
                                            class="w-4 h-4 transition-transform group-hover:translate-x-[-3px]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                        </svg>

                                        </a>

                                    </div>

                                </div>

                            </div>
                        </div>

                    @endforeach
                </div>
            </div>
        </section>
    @elseif($data['view'] == 2)
        <section class="relative transition-colors duration-500 overflow-hidden">

            <div class="container mx-auto relative z-10">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6 border-r-4 border-brown-600 pr-6">
                    <div>
                        <h2 class="text-3xl lg:text-4xl font-black text-gray-900 dark:text-white">{{$view}}  <span class="text-brown-600">محصولات</span></h2>
                        <p class="text-gray-500 dark:text-gray-400 mt-2 font-bold text-sm">برترین تکنولوژی‌های روز دنیا در دستان شما</p>
                    </div>
                </div>

                <div id="product-grid" class="grid pb-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                    @foreach($products as $product)
                        @php
                            $prices = $product->displayVariant?->priceData() ?? [
                                'price' => 0,
                                'after_discount' => 0,
                                'has_discount' => false,
                                'discount_percent' => 0,
                            ];
                        @endphp
                        <div class="group relative bg-white/70 dark:bg-white/[0.03] backdrop-blur-md rounded-[2.5rem] border border-gray-200 dark:border-white/10 p-2 transition-all duration-500 hover:shadow-lg hover:shadow-brown-600/20 hover:-translate-y-2"
                             data-categories="{{ $product->categories->pluck('slug')->implode(' ') }}">
                            <div class="flex h-[220px]">
                                <div class="w-2/5 relative overflow-hidden rounded-[2rem] m-1">
                                    <img
                                        src="{{ $product->featuredImageUrl }}"
                                        class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                                        alt="{{ $product->title }}"
                                    >

                                    @if($prices['has_discount'])
                                        <div class="absolute top-3 right-3 bg-red-500 text-white text-[10px] font-black px-2.5 py-1 rounded-lg shadow-lg shadow-red-500/40">
                                            {{ $prices['discount_percent'] }}٪-
                                        </div>
                                    @endif
                                </div>

                                <div class="w-3/5 p-5 flex flex-col justify-between">
                                    <div>
                                        <div class="flex justify-between items-start">
                                            <span class="text-[10px] font-bold text-brown-600 dark:text-brown-400 tracking-tighter opacity-80 mb-1 block">{{ $product->brand?->title ?? 'محصول' }}</span>
                                            <div class="flex gap-1">
                                                <div class="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_5px_rgba(59,130,246,0.5)]"></div>
                                                <div class="w-2 h-2 rounded-full bg-gray-800 shadow-[0_0_5px_rgba(0,0,0,0.5)]"></div>
                                            </div>
                                        </div>
                                        <h3 class="font-black text-gray-900 dark:text-white text-base leading-tight mb-2">{{ $product->title }}</h3>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 font-medium line-clamp-2 leading-relaxed">تراشه A17 Pro و بدنه تیتانیوم</p>
                                    </div>

                                    <div class="mt-auto">
                                        <div class="mb-3 text-left">
                                            @if($prices['has_discount'])
                                                <p class="text-[10px] text-gray-400 line-through mb-0.5">{{ number_format($prices['price'] ?? 0) }}</p>
                                            @endif
                                            <div class="flex items-baseline justify-end gap-1">
                                                <span class="text-xl font-black text-gray-900 dark:text-white tracking-tighter">{{ number_format($prices['after_discount'] ?? 0) }}</span>
                                                <span class="text-[10px] font-bold text-gray-500">تومان</span>
                                            </div>
                                        </div>

                                        <a
                                            href="{{ route('products.show', $product->slug) }}"
                                            class="w-full py-3 bg-brown-500 text-white rounded-xl text-[11px] font-black shadow-lg shadow-brown-500/20 hover:bg-brown-700 transition-all flex items-center justify-center gap-2 group/btn"
                                        >
                                            <span>خرید سریع</span>

                                            <svg
                                                class="w-4 h-4 transition-transform group-hover/btn:translate-x-[-3px]"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @elseif($data['view'] == 3)
        <section class="best-sellers-glass relative overflow-hidden transition-colors duration-700">
            <div class="container pb-7 relative z-10">

                <div class="flex items-end justify-between mb-6 gap-4 flex-wrap">
                    <div class="flex items-center gap-6">
                        <div class="relative group">
                            <div class="relative w-16 h-16 bg-white dark:bg-black border border-gray-100 dark:border-brown-500/40 rounded-[1.8rem] flex items-center justify-center text-brown-600 shadow-2xl">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h2 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ $view }}</h2>
                            <p class="text-[10px] font-black text-brown-500 uppercase tracking-[0.4em] mt-2 flex items-center gap-2">
                                <span class="w-8 h-[2px] bg-brown-500/30"></span>
                                Premium Selection
                            </p>
                        </div>
                    </div>

                    <a href="#" class="group/link relative overflow-hidden px-8 py-3.5 rounded-2xl transition-all duration-500 flex items-center gap-3 bg-white/40 backdrop-blur-md border border-gray-200 text-gray-800 hover:border-brown-500/50 hover:text-white dark:bg-white/[0.03] dark:border-white/10 dark:text-gray-300 dark:hover:text-white">
                        <span class="absolute inset-0 bg-brown-600 translate-y-full group-hover/link:translate-y-0 transition-transform duration-500 ease-out"></span>
                        <span class="relative z-10 text-[13px] font-black tracking-tight">مشاهده همه محصولات</span>
                        <div class="relative z-10 w-5 h-5 flex items-center justify-center bg-brown-600/10 dark:bg-white/5 rounded-lg group-hover/link:bg-white/20 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </div>
                    </a>
                </div>

                <div class="swiper productSwiper !overflow-visible">
                    <div class="swiper-wrapper">
                        @foreach($products as $product)
                            @php
                                $prices = $product->cheapestVariant?->priceData() ?? [
                                    'price' => 0,
                                    'after_discount' => 0,
                                    'has_discount' => false,
                                    'discount_percent' => 0,
                                ];
                            @endphp
                            <div class="swiper-slide h-auto p-4">
                                <div class="group relative h-full pt-12">
                                    <div class="absolute inset-0 bg-white/80 dark:bg-[#0a0f0a]/40 backdrop-blur-[20px] rounded-[3rem] border border-gray-100 dark:border-white/[0.08] shadow-[0_20px_50px_rgba(0,0,0,0.02)] transition-all duration-700 group-hover:border-brown-500/50 dark:group-hover:shadow-[0_0_60px_rgba(37,99,235,0.12)]"></div>

                                    <div class="relative p-7 flex flex-col h-full z-10 transition-transform duration-500 group-hover:-translate-y-4">
                                        @if($prices['has_discount'])
                                            <div class="absolute -top-6 -right-2 z-20">
                                                <div class="bg-secondary-500 dark:bg-[#ff1744] text-white text-[12px] font-black w-12 h-12 rounded-[1.2rem] flex items-center justify-center shadow-lg shadow-red-500/40 dark:shadow-[#ff1744]/30 rotate-12 group-hover:rotate-0 transition-all duration-500 border-2 border-white dark:border-white/20">
                                                    {{ $prices['discount_percent'] }}٪-
                                                </div>
                                            </div>
                                        @endif

                                            <div class="relative mb-8 flex items-center justify-center min-h-[180px]">

                                                @if(($data['pictureMode'] ?? 'transparent') === 'background')

                                                    {{-- حالت عکس با بک‌گراند --}}
                                                    <div class="relative w-full h-44 rounded-[2rem] overflow-hidden">
                                                        <img src="{{ $product->featuredImageUrl }}"
                                                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                                                             alt="{{ $product->title }}">

                                                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                                                        <div class="absolute inset-0 rounded-[2rem] ring-1 ring-inset ring-black/5 dark:ring-white/10"></div>
                                                    </div>

                                                @else

                                                    {{-- حالت عکس بدون بک‌گراند (transparent) --}}
                                                    <div class="absolute w-40 h-40 bg-brown-500/20 dark:bg-indigo-500/20 blur-[70px] rounded-full opacity-0 group-hover:opacity-100 transition-all duration-1000"></div>

                                                    <img src="{{ $product->featuredImageUrl }}"
                                                         class="relative z-10 w-full h-44 object-contain transition-all duration-700 group-hover:scale-110 group-hover:drop-shadow-brown"
                                                         alt="{{ $product->title }}">

                                                @endif

                                                <div class="absolute top-0 -left-2 z-20 flex flex-col gap-3 opacity-0 group-hover:opacity-100 -translate-x-4 group-hover:translate-x-0 transition-all duration-500">
                                                    <div class="relative flex items-center group/tooltip">
                                                        <a href="{{ route('products.show', $product->slug) }}" class="w-10 h-10 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md text-gray-900 dark:text-white rounded-xl flex items-center justify-center shadow-sm border border-white dark:border-white/10 hover:bg-secondary-500 dark:hover:bg-secondary-500 hover:text-white transition-all">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                            </svg>
                                                        </a>
                                                        <span class="absolute right-full mr-3 whitespace-nowrap bg-gray-900 dark:bg-zinc-800 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 pointer-events-none translate-x-2 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-x-0 transition-all duration-300 border border-white/5 after:content-[''] after:absolute after:top-1/2 after:-translate-y-1/2 after:-right-1 after:border-4 after:border-transparent after:border-l-gray-900 dark:after:border-l-zinc-800">
                مشاهده سریع
            </span>
                                                    </div>

                                                    <div class="relative flex items-center group/tooltip">
                                                        <button class="w-10 h-10 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md text-gray-900 dark:text-white rounded-xl flex items-center justify-center shadow-sm border border-white dark:border-white/10 hover:text-red-500 transition-all">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                                            </svg>
                                                        </button>
                                                        <span class="absolute right-full mr-3 whitespace-nowrap bg-gray-900 dark:bg-zinc-800 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 pointer-events-none translate-x-2 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-x-0 transition-all duration-300 border border-white/5 after:content-[''] after:absolute after:top-1/2 after:-translate-y-1/2 after:-right-1 after:border-4 after:border-transparent after:border-l-gray-900 dark:after:border-l-zinc-800">
                افزودن به علاقه‌مندی
            </span>
                                                    </div>
                                                </div>
                                            </div>

                                        <h3 class="text-[15px] font-black text-gray-800 dark:text-zinc-100 mb-6 line-clamp-2 leading-7 h-14 group-hover:text-brown-600 dark:group-hover:text-brown-400 transition-colors">
                                            {{ $product->title }}
                                        </h3>

                                        <div class="flex items-center justify-between mt-auto pt-5 border-t border-gray-100 dark:border-white/5">
                                            <div class="flex flex-col gap-1">
                                                @if($prices['has_discount'])
                                                    <span class="text-[11px] text-gray-400 dark:text-zinc-500 line-through tabular-nums leading-none">{{ number_format($prices['price'] ?? 0) }}</span>
                                                @endif
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-2xl font-black text-gray-900 dark:text-white tracking-tighter tabular-nums">{{ number_format($prices['after_discount'] ?? 0) }}</span>
                                                    <span class="text-[10px] text-gray-400 dark:text-zinc-500 font-bold uppercase">تومان</span>
                                                </div>
                                            </div>

                                            <a href="{{ route('products.show', $product->slug) }}" class="w-14 h-14 bg-brown-500 dark:bg-brown-600 text-white rounded-[1.5rem] flex items-center justify-center shadow-lg dark:shadow-[0_0_25px_rgba(37,99,235,0.3)] hover:scale-110 active:scale-90 transition-all group/btn relative overflow-hidden">
                                                <svg
                                                    class="w-5 h-5 rotate-180"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        d="M5 12h14m-6-6 6 6-6 6"
                                                        stroke-width="2.5"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/25 to-transparent -translate-x-full group-hover/btn:animate-[shimmer_2s_infinite]"></div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-center gap-4 mt-10">
                    <div class="prod-prev w-14 h-14 rounded-2xl bg-white/50 dark:bg-white/5 dark:text-white border border-white dark:border-white/10 flex items-center justify-center cursor-pointer hover:bg-brown-600 hover:text-white transition-all shadow-lg group">
                        <svg class="w-6 h-6 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    </div>
                    <div class="prod-next w-14 h-14 rounded-2xl bg-white/50 dark:bg-white/5 dark:text-white border border-white dark:border-white/10 flex items-center justify-center cursor-pointer hover:bg-brown-600 hover:text-white transition-all shadow-lg group">
                        <svg class="w-6 h-6 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>
