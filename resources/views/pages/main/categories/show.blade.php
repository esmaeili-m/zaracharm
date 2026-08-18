<?php

use Livewire\Component;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public $category,$products,$categories;
    public $sort = 'latest';
    public $categoryIds = [];
    public array $selectedCategories = [];
    public function sortBy($sort)
    {
        $this->sort = $sort;

        $query = Product::query()
            ->whereHas('categories', function ($query) {
                $query->whereIn('categories.id', $this->categoryIds);
            });

        if (!empty($this->selectedCategories)) {

            $query->whereHas('categories', function ($query) {
                $query->whereIn('categories.id', $this->selectedCategories);
            });

        }
        switch ($this->sort) {

            case 'sales':

                $salesQuery = DB::table('product_variants')
                    ->join('order_items', 'product_variants.id', '=', 'order_items.variant_id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->whereNotIn('orders.status', ['cancelled'])
                    ->select(
                        'product_variants.product_id',
                        DB::raw('SUM(order_items.quantity) as total_sales')
                    )
                    ->groupBy('product_variants.product_id');

                $query
                    ->leftJoinSub(
                        $salesQuery,
                        'sales',
                        function ($join) {
                            $join->on('products.id', '=', 'sales.product_id');
                        }
                    )
                    ->select('products.*')
                    ->selectRaw('COALESCE(sales.total_sales, 0) as total_sales')
                    ->orderByDesc('total_sales');

                break;

            case 'cheap':

                $priceQuery = DB::table('product_variants')
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->select(
                        'product_id',
                        DB::raw('MIN(price) as min_price')
                    )
                    ->groupBy('product_id');

                $query
                    ->joinSub(
                        $priceQuery,
                        'prices',
                        function ($join) {
                            $join->on('products.id', '=', 'prices.product_id');
                        }
                    )
                    ->select('products.*')
                    ->selectRaw('prices.min_price')
                    ->orderBy('prices.min_price', 'asc');

                break;

            default:

                $query->latest('products.created_at');

                break;
        }

        $this->products = $query->get();
    }
    public function mount($slug)
    {
        $this->category = Category::query()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        $this->categories = $this->category
            ->children()
            ->active()
            ->orderBy('title')
            ->get();
        $this->categoryIds = $this->category
            ->getAllDescendantIds()
            ->push($this->category->id)
            ->unique()
            ->values()
            ->toArray();
        $categoryIds = $this->categoryIds;
        $this->products = Product::query()
            ->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('categories.id', $categoryIds);
            })
            ->get();
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
                       class="hover:text-blue-500 transition-colors">
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

                        <a href="{{ route('home', $category->parent->slug) }}"
                           class="hover:text-blue-500 transition-colors">
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

                    <span class="text-blue-600 dark:text-blue-400">
            {{ $category->title }}
        </span>

                </nav>


                {{-- Title + Sort --}}
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-8">

                    {{-- Title --}}
                    <div class="relative">

                        <div class="absolute -right-4 top-0 w-1 h-12
                        bg-blue-500 rounded-full blur-[2px]"></div>

                        <h1 class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">
                            {{ $category->title }}
                        </h1>

                        <div class="flex items-center gap-2 mt-3">

                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>

                            <p class="text-xs text-gray-500 dark:text-gray-400 font-bold">
                                نمایش
                                <span class="text-gray-800 dark:text-white">
                        {{ $products->count() }}
                    </span>
                                محصول موجود
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

                            <svg class="w-4 h-4 text-blue-500"
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


                        <div class="flex items-center gap-1">

                            {{-- جدیدترین --}}
                            <button
                                wire:click="sortBy('latest')"
                                class="px-5 py-2.5 rounded-[1.2rem] text-[11px] font-black
                           transition-all active:scale-95
                           {{ $sort === 'latest'
                                ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/25'
                                : 'text-gray-500 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-white/5'
                           }}"
                            >
                                جدیدترین
                            </button>


                            {{-- پرفروش‌ترین --}}
                            <button
                                wire:click="sortBy('sales')"
                                class="px-5 py-2.5 rounded-[1.2rem] text-[11px] font-black
                           transition-all active:scale-95
                           {{ $sort === 'sales'
                                ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/25'
                                : 'text-gray-500 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-white/5'
                           }}"
                            >
                                پرفروش‌ترین
                            </button>


                            {{-- ارزان‌ترین --}}
                            <button
                                wire:click="sortBy('cheap')"
                                class="px-5 py-2.5 rounded-[1.2rem] text-[11px] font-black
                           transition-all active:scale-95
                           {{ $sort === 'cheap'
                                ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/25'
                                : 'text-gray-500 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-white/5'
                           }}"
                            >
                                ارزان‌ترین
                            </button>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Filter Showing in Responsive Break Point -->
            <!--Open filters button on mobile-->
            <div class="fixed bottom-28 right-6 z-[95] lg:hidden">
                <button onclick="toggleFilters(true)" class="flex items-center justify-center w-14 h-14 bg-white/40 dark:bg-white/[0.05] backdrop-blur-md text-blue-600 rounded-2xl shadow-lg border border-white/60 dark:border-white/10 active:scale-90 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                    </svg>
                </button>
            </div>

            <!--Overlay for mobile-->
            <div id="filter-overlay" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-[140] opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>

            <!--Offcanvas Filters for Mobile-->
            <div id="filter-offcanvas" class="fixed top-0 right-0 h-full w-[85%] max-w-[380px] bg-white/30 dark:bg-black/40 backdrop-blur-[30px] z-[150] translate-x-full transition-transform duration-500 ease-in-out border-l border-white/40 dark:border-white/10 shadow-lg lg:hidden">
                <div class="flex flex-col h-full">
                    <div class="p-6 flex items-center justify-between border-b border-white/40 dark:border-white/5 bg-white/20 dark:bg-white/[0.02]">
                        <h2 class="text-lg font-black text-gray-900 dark:text-white">فیلترها</h2>
                        <button onclick="toggleFilters(false)" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white/40 dark:bg-white/5 text-gray-600 dark:text-gray-300 border border-white/60 dark:border-white/10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-8 custom-scrollbar">
                        <!--Category Mobile-->
                        <div class="relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
                            <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
                                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                    دسته‌بندی محصولات
                                </h3>
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] font-black text-blue-500 bg-blue-500/10 px-3 py-1 rounded-full uppercase tracking-widest">دیجیتال</span>
                                    <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>

                            <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
                                <ul class="px-7 pb-8 space-y-1">
                                    <li class="group/brand">
                                        <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-white/5 flex items-center justify-center text-gray-500 group-hover/brand:bg-blue-500 group-hover/brand:text-white transition-all">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                                </div>
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">گوشی موبایل</span>
                                            </div>
                                            <div class="relative flex items-center">
                                                <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer" checked>
                                                <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </div>
                                        </label>
                                    </li>

                                    <li class="group/brand">
                                        <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-white/5 flex items-center justify-center text-gray-500 group-hover/brand:bg-blue-500 group-hover/brand:text-white transition-all">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 21h6l-.75-4M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                </div>
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">لپ‌تاپ</span>
                                            </div>
                                            <div class="relative flex items-center">
                                                <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </div>
                                        </label>
                                    </li>

                                    <li class="group/brand">
                                        <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-white/5 flex items-center justify-center text-gray-500 group-hover/brand:bg-blue-500 group-hover/brand:text-white transition-all">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </div>
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">ساعت هوشمند</span>
                                            </div>
                                            <div class="relative flex items-center">
                                                <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </div>
                                        </label>
                                    </li>

                                    <li class="group/brand">
                                        <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-white/5 flex items-center justify-center text-gray-500 group-hover/brand:bg-blue-500 group-hover/brand:text-white transition-all">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path></svg>
                                                </div>
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">هدفون و هندزفری</span>
                                            </div>
                                            <div class="relative flex items-center">
                                                <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </div>
                                        </label>
                                    </li>

                                    <li class="group/brand">
                                        <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-white/5 flex items-center justify-center text-gray-500 group-hover/brand:bg-blue-500 group-hover/brand:text-white transition-all">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path></svg>
                                                </div>
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">تبلت</span>
                                            </div>
                                            <div class="relative flex items-center">
                                                <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </div>
                                        </label>
                                    </li>

                                    <li class="group/brand">
                                        <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-white/5 flex items-center justify-center text-gray-500 group-hover/brand:bg-blue-500 group-hover/brand:text-white transition-all">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"></path></svg>
                                                </div>
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">کنسول بازی</span>
                                            </div>
                                            <div class="relative flex items-center">
                                                <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </div>
                                        </label>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Price Range Mobile -->
                        <div class="js-price-range-container relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
                            <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
                                    <span class="w-3 h-3 rounded-full bg-secondary-500"></span>
                                    محدوده قیمت
                                </h3>
                                <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
                                <div class="px-7 pb-8 pt-3">
                                    <div class="relative w-[92%] mx-auto h-1.5 bg-gray-200 dark:bg-white/10 rounded-full mb-10 mt-4">
                                        <div class="js-slider-track absolute h-full bg-primary-500 rounded-full"></div>
                                        <input type="range" class="js-min-range range-input absolute w-full h-1.5 bg-transparent appearance-none pointer-events-none cursor-pointer z-20" min="0" max="100000000" value="0" step="100000">
                                        <input type="range" class="js-max-range range-input absolute w-full h-1.5 bg-transparent appearance-none pointer-events-none cursor-pointer z-20" min="0" max="100000000" value="100000000" step="100000">
                                    </div>
                                    <div class="space-y-4">
                                        <div class="group/in relative flex items-center bg-gray-50/50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-[1.25rem] px-4 py-3 transition-all focus-within:border-primary-500/50 focus-within:bg-white dark:focus-within:bg-white/10">
                                            <span class="text-[11px] font-bold text-gray-400 ml-3">از</span>
                                            <input type="text" class="js-min-price-input w-full bg-transparent border-none focus:ring-0 text-sm font-black text-gray-700 dark:text-white text-left p-0" value="0">
                                        </div>
                                        <div class="group/in relative flex items-center bg-gray-50/50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-[1.25rem] px-4 py-3 transition-all focus-within:border-primary-500/50 focus-within:bg-white dark:focus-within:bg-white/10">
                                            <span class="text-[11px] font-bold text-gray-400 ml-3">تا</span>
                                            <input type="text" class="js-max-price-input w-full bg-transparent border-none focus:ring-0 text-sm font-black text-gray-700 dark:text-white text-left p-0" value="100,000,000">
                                        </div>
                                    </div>
                                    <button class="w-full mt-8 py-4 bg-primary-500 hover:bg-primary-600 text-white rounded-[1.5rem] text-xs font-black transition-all active:scale-95">تایید محدوده قیمت</button>
                                </div>
                            </div>
                        </div>

                        <!-- Status Product Mobile -->
                        <div class="relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
                            <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
                                    <span class="w-3 h-3 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span>
                                    وضعیت کالا
                                </h3>
                                <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>

                            <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
                                <div class="px-7 pb-8 space-y-6">
                                    <label class="flex items-center justify-between cursor-pointer group/sw">
                                        <div class="flex flex-col gap-1.5">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-emerald-500 transition-colors">فقط کالاهای موجود</span>
                                            <span class="text-[10px] text-gray-400 font-medium">حذف کالاهای ناموجود از لیست</span>
                                        </div>
                                        <div class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer" checked>
                                            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500 shadow-sm"></div>
                                        </div>
                                    </label>

                                    <label class="flex items-center justify-between cursor-pointer group/sw">
                                        <div class="flex flex-col gap-1.5">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-rose-500 transition-colors">فقط کالاهای تخفیف‌دار</span>
                                            <span class="text-[10px] text-gray-400 font-medium">نمایش پیشنهادات ویژه و شگفت‌انگیز</span>
                                        </div>
                                        <div class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500"></div>
                                        </div>
                                    </label>

                                    <label class="flex items-center justify-between cursor-pointer group/sw">
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-blue-500 transition-colors">ارسال فوری (مانا جت)</span>
                                                <span class="flex h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
                                            </div>
                                            <span class="text-[10px] text-gray-400 font-medium">تحویل در کمتر از ۳ ساعت در تهران</span>
                                        </div>
                                        <div class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        </div>
                                    </label>

                                    <label class="flex items-center justify-between cursor-pointer group/sw">
                                        <div class="flex flex-col gap-1.5">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-purple-500 transition-colors">کالاهای با گارانتی اصلی</span>
                                            <span class="text-[10px] text-gray-400 font-medium">فقط محصولات تضمین شده توسط مانا</span>
                                        </div>
                                        <div class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-500"></div>
                                        </div>
                                    </label>

                                    <label class="flex items-center justify-between cursor-pointer group/sw">
                                        <div class="flex flex-col gap-1.5">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-secondary-500 transition-colors">جدیدترین محصولات</span>
                                            <span class="text-[10px] text-gray-400 font-medium">محصولات اضافه شده در ۷ روز اخیر</span>
                                        </div>
                                        <div class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-secondary-500"></div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Popular Brand Mobile -->
                        <div class="relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
                            <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
                                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                    برندهای محبوب
                                </h3>
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] font-black text-blue-500 bg-blue-500/10 px-3 py-1 rounded-full uppercase tracking-widest">همه</span>
                                    <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>

                            <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
                                <div class="px-7 pb-8 space-y-5">
                                    <div class="relative group/search">
                                        <input type="text" class="js-brand-search w-full bg-white/60 dark:bg-white/10 border border-gray-200 dark:border-white/20 rounded-2xl py-3.5 pr-11 pl-4 text-xs font-bold text-gray-800 dark:text-white outline-none transition-all focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/5" placeholder="جستجوی برند...">
                                        <div class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                        </div>
                                    </div>

                                    <ul class="js-brands-list space-y-1 max-h-72 overflow-y-auto custom-scrollbar pl-2">
                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">اپل (Apple)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>

                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">سامسونگ (Samsung)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer" checked>
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>

                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">شیائومی (Xiaomi)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>

                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">سونی (Sony)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>

                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">هواوی (Huawei)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>

                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">مایکروسافت (Microsoft)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 bg-white/30 dark:bg-black/20 border-t border-white/40 dark:border-white/5">
                        <button onclick="toggleFilters(false)" class="w-full bg-blue-600 text-white py-4 rounded-[1.8rem] font-black shadow-lg shadow-blue-600/30 active:scale-95 transition-all">اعمال فیلترها</button>
                    </div>
                </div>
            </div>
            <!-- End Filter Showing in Responsive Break Point -->


            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

                <aside class="hidden lg:block lg:col-span-1 relative">
                    <div class="sticky top-10 space-y-6">
                        <!--Category-->
                        <div class="relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
                            <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
                                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                    دسته‌بندی محصولات
                                </h3>
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] font-black text-blue-500 bg-blue-500/10 px-3 py-1 rounded-full uppercase tracking-widest">{{$this->category->title}}</span>
                                    <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>

                            @if($categories->isNotEmpty())

                                <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out"
                                     style="max-height: 1000px; opacity: 1;">

                                    <ul class="px-7 pb-8 space-y-1">

                                        @foreach($categories as $child)

                                            <li class="group/category">

                                                <label
                                                    class="flex items-center justify-between p-3 rounded-2xl
                               hover:bg-blue-500/5 dark:hover:bg-blue-500/10
                               cursor-pointer transition-all border border-transparent
                               hover:border-blue-500/20"
                                                >

                                                    <div class="flex items-center gap-3">

                                                        <div
                                                            class="w-8 h-8 rounded-xl
                                       bg-gray-100 dark:bg-white/5
                                       flex items-center justify-center
                                       text-gray-500
                                       group-hover/category:bg-blue-500
                                       group-hover/category:text-white
                                       transition-all"
                                                        >
                                                            <svg class="w-4 h-4"
                                                                 fill="none"
                                                                 stroke="currentColor"
                                                                 viewBox="0 0 24 24">
                                                                <path
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M6 8h12l1 12H5L6 8z
           M9 8a3 3 0 016 0
           M9 12h.01
           M15 12h.01"
                                                                />
                                                            </svg>
                                                        </div>

                                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300
                                         group-hover/category:text-blue-600 transition-colors">
                                {{ $child->title }}
                            </span>

                                                    </div>

                                                    <div class="relative flex items-center">

                                                        <input
                                                            type="checkbox"
                                                            value="{{ $child->id }}"
                                                            wire:model.live="selectedCategories"
                                                            class="peer appearance-none w-5 h-5 rounded-lg
                                       border-2 border-gray-300/70
                                       dark:border-white/20
                                       checked:bg-blue-500
                                       checked:border-blue-500
                                       transition-all cursor-pointer"
                                                        >

                                                        <svg
                                                            class="absolute w-3 h-3 text-white opacity-0
                                       peer-checked:opacity-100 right-1
                                       pointer-events-none transition-opacity"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                d="M5 13l4 4L19 7"
                                                                stroke-width="4"
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                            />
                                                        </svg>

                                                    </div>

                                                </label>

                                            </li>

                                        @endforeach

                                    </ul>

                                </div>

                            @endif
                        </div>

                        <!-- Price Range Desktop -->
                        <div class="js-price-range-container relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
                            <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
                                    <span class="w-3 h-3 rounded-full bg-secondary-500"></span>
                                    محدوده قیمت
                                </h3>
                                <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
                                <div class="px-7 pb-8 pt-3">
                                    <div class="relative w-[92%] mx-auto h-1.5 bg-gray-200 dark:bg-white/10 rounded-full mb-10 mt-4">
                                        <div class="js-slider-track absolute h-full bg-primary-500 rounded-full"></div>
                                        <input type="range" class="js-min-range range-input absolute w-full h-1.5 bg-transparent appearance-none pointer-events-none cursor-pointer z-20" min="0" max="100000000" value="0" step="100000">
                                        <input type="range" class="js-max-range range-input absolute w-full h-1.5 bg-transparent appearance-none pointer-events-none cursor-pointer z-20" min="0" max="100000000" value="100000000" step="100000">
                                    </div>
                                    <div class="space-y-4">
                                        <div class="group/in relative flex items-center bg-gray-50/50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-[1.25rem] px-4 py-3 transition-all focus-within:border-primary-500/50 focus-within:bg-white dark:focus-within:bg-white/10">
                                            <span class="text-[11px] font-bold text-gray-400 ml-3">از</span>
                                            <input type="text" class="js-min-price-input w-full bg-transparent border-none focus:ring-0 text-sm font-black text-gray-700 dark:text-white text-left p-0" value="0">
                                        </div>
                                        <div class="group/in relative flex items-center bg-gray-50/50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-[1.25rem] px-4 py-3 transition-all focus-within:border-primary-500/50 focus-within:bg-white dark:focus-within:bg-white/10">
                                            <span class="text-[11px] font-bold text-gray-400 ml-3">تا</span>
                                            <input type="text" class="js-max-price-input w-full bg-transparent border-none focus:ring-0 text-sm font-black text-gray-700 dark:text-white text-left p-0" value="100,000,000">
                                        </div>
                                    </div>
                                    <button class="w-full mt-8 py-4 bg-primary-500 hover:bg-primary-600 text-white rounded-[1.5rem] text-xs font-black transition-all active:scale-95">تایید محدوده قیمت</button>
                                </div>
                            </div>
                        </div>

                        <!-- Status Product -->
                        <div class="relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
                            <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
                                    <span class="w-3 h-3 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span>
                                    وضعیت کالا
                                </h3>
                                <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>

                            <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
                                <div class="px-7 pb-8 space-y-6">
                                    <label class="flex items-center justify-between cursor-pointer group/sw">
                                        <div class="flex flex-col gap-1.5">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-emerald-500 transition-colors">فقط کالاهای موجود</span>
                                            <span class="text-[10px] text-gray-400 font-medium">حذف کالاهای ناموجود از لیست</span>
                                        </div>
                                        <div class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer" checked>
                                            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500 shadow-sm"></div>
                                        </div>
                                    </label>

                                    <label class="flex items-center justify-between cursor-pointer group/sw">
                                        <div class="flex flex-col gap-1.5">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-rose-500 transition-colors">فقط کالاهای تخفیف‌دار</span>
                                            <span class="text-[10px] text-gray-400 font-medium">نمایش پیشنهادات ویژه و شگفت‌انگیز</span>
                                        </div>
                                        <div class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500"></div>
                                        </div>
                                    </label>

                                    <label class="flex items-center justify-between cursor-pointer group/sw">
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-blue-500 transition-colors">ارسال فوری (مانا جت)</span>
                                                <span class="flex h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
                                            </div>
                                            <span class="text-[10px] text-gray-400 font-medium">تحویل در کمتر از ۳ ساعت در تهران</span>
                                        </div>
                                        <div class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        </div>
                                    </label>

                                    <label class="flex items-center justify-between cursor-pointer group/sw">
                                        <div class="flex flex-col gap-1.5">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-purple-500 transition-colors">کالاهای با گارانتی اصلی</span>
                                            <span class="text-[10px] text-gray-400 font-medium">فقط محصولات تضمین شده توسط مانا</span>
                                        </div>
                                        <div class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-500"></div>
                                        </div>
                                    </label>

                                    <label class="flex items-center justify-between cursor-pointer group/sw">
                                        <div class="flex flex-col gap-1.5">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-secondary-500 transition-colors">جدیدترین محصولات</span>
                                            <span class="text-[10px] text-gray-400 font-medium">محصولات اضافه شده در ۷ روز اخیر</span>
                                        </div>
                                        <div class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-secondary-500"></div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Popular Brand Desktop -->
                        <div class="relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
                            <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
                                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                    برندهای محبوب
                                </h3>
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] font-black text-blue-500 bg-blue-500/10 px-3 py-1 rounded-full uppercase tracking-widest">همه</span>
                                    <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>

                            <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
                                <div class="px-7 pb-8 space-y-5">
                                    <div class="relative group/search">
                                        <input type="text" class="js-brand-search w-full bg-white/60 dark:bg-white/10 border border-gray-200 dark:border-white/20 rounded-2xl py-3.5 pr-11 pl-4 text-xs font-bold text-gray-800 dark:text-white outline-none transition-all focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/5" placeholder="جستجوی برند...">
                                        <div class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                        </div>
                                    </div>

                                    <ul class="js-brands-list space-y-1 max-h-72 overflow-y-auto custom-scrollbar pl-2">
                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">اپل (Apple)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>

                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">سامسونگ (Samsung)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer" checked>
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>

                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">شیائومی (Xiaomi)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>

                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">سونی (Sony)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>

                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">هواوی (Huawei)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>

                                        <li class="group/brand">
                                            <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">مایکروسافت (Microsoft)</span>
                                                <div class="relative flex items-center">
                                                    <input type="checkbox" class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer">
                                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            </label>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <div class="lg:col-span-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">

                        @forelse($products as $product)

                            @php
                                $variant = $product->variants
                                    ->where('status', 1)
                                    ->where('stock', '>', 0)
                                    ->sortBy(function ($variant) {
                                        return $variant->final_price ?? $variant->price;
                                    })
                                    ->first();

                                $price = $variant?->price ?? 0;
                                $finalPrice = $variant?->final_price ?? $price;

                                $discountPercent = 0;

                                if ($price > 0 && $finalPrice < $price) {
                                    $discountPercent = round(
                                        (($price - $finalPrice) / $price) * 100
                                    );
                                }

                                $image = $product->media
                                    ->where('collection', 'product')
                                    ->first();
                            @endphp


                            <div class="group relative h-full pt-12">

                                {{-- Card Background --}}
                                <div
                                    class="absolute inset-0
                       bg-white/80 dark:bg-[#0a0a0a]/40
                       backdrop-blur-[20px]
                       rounded-[3rem]
                       border border-gray-100 dark:border-white/[0.08]
                       shadow-[0_20px_50px_rgba(0,0,0,0.02)]
                       transition-all duration-700
                       group-hover:border-blue-500/50
                       dark:group-hover:shadow-[0_0_60px_rgba(37,99,235,0.12)]"
                                ></div>


                                <div
                                    class="relative p-7 flex flex-col h-full z-10
                       transition-transform duration-500
                       group-hover:-translate-y-4"
                                >

                                    {{-- Discount --}}
                                    @if($discountPercent > 0)

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
                                                {{ $discountPercent }}٪
                                            </div>

                                        </div>

                                    @endif


                                    {{-- Image --}}
                                    <div class="relative mb-8 flex items-center justify-center min-h-[180px]">

                                        <div
                                            class="absolute w-40 h-40
                               bg-blue-500/20 dark:bg-indigo-500/20
                               blur-[70px] rounded-full
                               opacity-0 group-hover:opacity-100
                               transition-all duration-1000"
                                        ></div>


                                        @if($image)

                                            <img
                                                src="{{ asset('storage/' . $image->file_path) }}"
                                                class="relative z-10 w-full h-44 object-contain
                                   transition-all duration-700
                                   group-hover:scale-110
                                   group-hover:drop-shadow-[0_15px_35px_rgba(37,99,235,0.3)]"
                                                alt="{{ $product->title }}"
                                            >

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

                                                <button
                                                    type="button"
                                                    class="w-10 h-10 quick-view-btn
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
                                مشاهده سریع
                            </span>

                                            </div>


                                            {{-- Favorite --}}
                                            <div class="relative flex items-center group/tooltip">

                                                <button
                                                    type="button"
                                                    class="w-10 h-10
                                       bg-white/90 dark:bg-zinc-900/90
                                       backdrop-blur-md
                                       text-gray-900 dark:text-white
                                       rounded-xl flex items-center justify-center
                                       shadow-sm border border-white dark:border-white/10
                                       hover:text-red-500 transition-all"
                                                >

                                                    <svg class="w-5 h-5"
                                                         fill="none"
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
                                افزودن به علاقه‌مندی
                            </span>

                                            </div>

                                        </div>

                                    </div>


                                    {{-- Title --}}
                                    <h3
                                        class="text-[15px] font-black
                           text-gray-800 dark:text-zinc-100
                           mb-6 line-clamp-2 leading-7 h-14
                           group-hover:text-blue-600
                           dark:group-hover:text-blue-400
                           transition-colors"
                                    >
                                        {{ $product->title }}
                                    </h3>


                                    {{-- Price --}}
                                    <div
                                        class="flex items-center justify-between
                           mt-auto pt-5
                           border-t border-gray-100 dark:border-white/5"
                                    >

                                        <div class="flex flex-col gap-1">

                                            @if($discountPercent > 0)

                                                <span
                                                    class="text-[11px] text-gray-400 dark:text-zinc-500
                                       line-through tabular-nums leading-none"
                                                >
                                {{ number_format($price) }}
                            </span>

                                            @endif


                                            <div class="flex items-center gap-1.5">

                            <span
                                class="text-2xl font-black
                                       text-gray-900 dark:text-white
                                       tracking-tighter tabular-nums"
                            >
                                {{ number_format($finalPrice) }}
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
                                        @if($variant)

                                            <button
                                                type="button"
                                                wire:click="addToCart({{ $variant->id }})"
                                                wire:loading.attr="disabled"
                                                class="w-14 h-14
                                   bg-primary-500 dark:bg-blue-600
                                   text-white rounded-[1.5rem]
                                   flex items-center justify-center
                                   shadow-lg
                                   dark:shadow-[0_0_25px_rgba(37,99,235,0.3)]
                                   hover:scale-110 active:scale-90
                                   transition-all
                                   group/btn relative overflow-hidden"
                                            >

                                                <svg
                                                    wire:loading.remove
                                                    class="w-6 h-6 relative z-10"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2.5"
                                                        d="M12 4v16m8-8H4"
                                                    />
                                                </svg>

                                                <svg
                                                    wire:loading
                                                    class="w-5 h-5 animate-spin"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <circle
                                                        class="opacity-25"
                                                        cx="12"
                                                        cy="12"
                                                        r="10"
                                                        stroke="currentColor"
                                                        stroke-width="4"
                                                    />

                                                    <path
                                                        class="opacity-75"
                                                        fill="currentColor"
                                                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                                    />
                                                </svg>

                                                <div
                                                    class="absolute inset-0
                                       bg-gradient-to-r
                                       from-transparent via-white/25 to-transparent
                                       -translate-x-full
                                       group-hover/btn:animate-[shimmer_2s_infinite]"
                                                ></div>

                                            </button>

                                        @else

                                            <span
                                                class="px-4 py-3 rounded-2xl
                                   bg-gray-100 dark:bg-white/5
                                   text-[10px] font-black
                                   text-gray-400"
                                            >
                            ناموجود
                        </span>

                                        @endif

                                    </div>

                                </div>

                            </div>

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

                            </div>

                        @endforelse

                    </div>

                    <!-- Pagination -->
                    <div class="mt-16 flex items-center justify-center">
                        <div class="flex items-center gap-2 bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 p-2 rounded-[2rem] shadow-lg shadow-gray-200/40 dark:shadow-none">

                            <button class="w-11 h-11 rounded-[1.2rem] bg-white/50 dark:bg-white/5 border border-gray-100 dark:border-white/5 flex items-center justify-center text-gray-400 hover:text-blue-500 hover:bg-white dark:hover:bg-white/10 transition-all group">
                                <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>

                            <div class="flex items-center gap-1.5 px-2">
                                <button class="w-11 h-11 rounded-[1.2rem] bg-white/60 dark:bg-white/10 border border-white dark:border-white/5 flex items-center justify-center text-xs font-black text-gray-600 dark:text-gray-300 hover:bg-blue-500 hover:text-white hover:shadow-lg hover:shadow-blue-500/30 transition-all">۱</button>

                                <button class="w-11 h-11 rounded-[1.2rem] bg-blue-500 text-white flex items-center justify-center text-xs font-black shadow-lg shadow-blue-500/40 ring-4 ring-blue-500/10">۲</button>

                                <button class="w-11 h-11 rounded-[1.2rem] bg-white/60 dark:bg-white/10 border border-white dark:border-white/5 flex items-center justify-center text-xs font-black text-gray-600 dark:text-gray-300 hover:bg-blue-500 hover:text-white hover:shadow-lg hover:shadow-blue-500/30 transition-all">۳</button>

                                <span class="px-2 text-gray-400 font-black">...</span>

                                <button class="w-11 h-11 rounded-[1.2rem] bg-white/60 dark:bg-white/10 border border-white dark:border-white/5 flex items-center justify-center text-xs font-black text-gray-600 dark:text-gray-300 hover:bg-blue-500 hover:text-white hover:shadow-lg hover:shadow-blue-500/30 transition-all">۱۲</button>
                            </div>

                            <button class="w-11 h-11 rounded-[1.2rem] bg-white/50 dark:bg-white/5 border border-gray-100 dark:border-white/5 flex items-center justify-center text-gray-400 hover:text-blue-500 hover:bg-white dark:hover:bg-white/10 transition-all group">
                                <svg class="w-5 h-5 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>

                        </div>
                    </div>

                </div>
            </div>


        </div>

    </section>

</div>
