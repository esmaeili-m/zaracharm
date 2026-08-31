<?php

use Livewire\Component;
use App\Models\Category;
new class extends Component
{
    public $data;
    public $categories=[];
    public $colors=[];
    public function mount($data)
    {
        $this->colors = ['brown', 'red', 'green', 'purple', 'pink', 'cyan', 'yellow', 'indigo'];
        $this->data = $data;
        if ($data){
            if ($data['mode'] == 'sales') {
                $this->categories = Category::query()
                    ->select([
                        'categories.id',
                        'categories.title',
                    ])
                    ->join(
                        'category_product',
                        'category_product.category_id',
                        '=',
                        'categories.id'
                    )
                    ->join(
                        'product_variants',
                        'product_variants.product_id',
                        '=',
                        'category_product.product_id'
                    )
                    ->join(
                        'order_items',
                        'order_items.variant_id',
                        '=',
                        'product_variants.id'
                    )
                    ->selectRaw('SUM(order_items.quantity) as total_sales')
                    ->groupBy([
                        'categories.id',
                        'categories.title',
                    ])
                    ->orderByDesc('total_sales')
                    ->take($data['limit'] ?? 6)
                    ->get();
            } elseif ($data['mode'] == 'views') {
                $this->categories = Category::query()
                    ->select([
                        'categories.id',
                        'categories.title',
                    ])
                    ->join(
                        'category_product',
                        'category_product.category_id',
                        '=',
                        'categories.id'
                    )
                    ->join(
                        'products',
                        'products.id',
                        '=',
                        'category_product.product_id'
                    )
                    ->leftJoin(
                        'views',
                        function ($join) {
                            $join->on('views.viewable_id', '=', 'products.id')
                                ->where('views.viewable_type', '=', \App\Models\Product::class);
                        }
                    )
                    ->selectRaw('COUNT(views.id) as total_views')
                    ->groupBy([
                        'categories.id',
                        'categories.title',
                    ])
                    ->orderByDesc('total_views')
                    ->take($data['limit'] ?? 6)
                    ->get();
            } elseif ($data['mode'] == 'random') {

                $this->categories = Category::query()->inRandomOrder()->whereNull('parent_id')
                    ->take($data['limit'] ?? 6)
                    ->get();
            }elseif ($data['mode'] == 'latest') {
                $this->categories = Category::query()->orderBy('sort')->latest()->whereNull('parent_id')
                    ->take($data['limit'] ?? 6)
                    ->get();

            }elseif ($data['mode'] == 'all') {
                $this->categories = Category::query()->orderBy('sort')->latest()->get();
            } else {

                $this->categories = Category::whereIn(
                    'id',
                    $data['category_ids'] ?? []
                )
                    ->take($data['limit'] ?? 6)
                    ->get();

            }
        }
    }
};
?>

<div>
    <section class="relative overflow-hidden  transition-colors duration-500">
        <div class="container py-4 relative z-10">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6">
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="px-3 py-1 bg-brown/10 text-brown text-[10px] font-black rounded-full tracking-widest uppercase">دسترسی سریع</span>
                        <div class="h-[1px] w-12 bg-brown/30"></div>
                    </div>
                    <h2 class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">
                        دسته‌بندی‌های <span class="relative">
                        <span class="relative z-10">محبوب</span>
                        <span class="absolute bottom-2 inset-x-0 h-3 bg-brown/20 -z-10 rounded-full"></span>
                    </span>
                    </h2>
                </div>


                <a href="/categories" class="group/link relative overflow-hidden px-8 py-3.5 rounded-2xl transition-all duration-500 flex items-center gap-3 bg-white/40 backdrop-blur-md border border-gray-200 text-gray-800 hover:border-brown-500/50 hover:text-white dark:bg-white/[0.03] dark:border-white/10 dark:text-gray-300 dark:hover:text-white">

                    <span class="absolute inset-0 bg-brown-600 translate-y-full group-hover/link:translate-y-0 transition-transform duration-500 ease-out"></span>

                    <span class="relative z-10 text-[13px] font-black tracking-tight">مشاهده تمامی دسته‌ها</span>

                    <div class="relative z-10 w-5 h-5 flex items-center justify-center bg-brown-600/10 dark:bg-white/5 rounded-lg group-hover/link:bg-white/20 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </div>
                </a>
            </div>
            @if($data)
                @if((int) $data['view'] == 1)
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-6">
                    @foreach($categories ?? [] as $category)
                        @if(($data['pictureMode'] ?? 'background') === 'background')
                            {{-- Background Image --}}
                            <a href="#" class="group relative flex flex-col items-center">

                                <div class="relative w-full aspect-square max-w-[220px] overflow-hidden rounded-[3rem] mb-6">

                                    {{-- Background --}}
                                    <img
                                        src="{{ $category->featuredImageUrl }}"
                                        alt="{{ $category->title }}"
                                        class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                                    >

                                    {{-- Overlay --}}
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-all duration-500"></div>

                                </div>

                                <span class="text-[13px] font-black text-gray-500 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors duration-300">
            {{ $category->title }}
        </span>

                                <div class="mt-4 w-1.5 h-1.5 bg-brown rounded-full opacity-0 group-hover:opacity-100 group-hover:scale-[3] transition-all duration-500"></div>

                            </a>

                        @else

                            {{-- Transparent Image --}}
                            <a href="#" class="group relative flex flex-col items-center">

                                <div class="relative w-full aspect-square max-w-[220px] flex items-center justify-center mb-6">

                                    <div class="absolute inset-0 bg-white/60 dark:bg-white/[0.03] backdrop-blur-md rounded-[3rem] border border-white dark:border-white/10 shadow-[0_20px_40px_-15px_rgba(0,0,0,0.05)] transition-all duration-700 group-hover:-translate-y-4 group-hover:bg-white/80 dark:group-hover:bg-white/[0.07] group-hover:shadow-lg group-hover:shadow-brown/20 group-hover:border-brown/30">
                                    </div>

                                    <div class="relative z-10 flex items-center justify-center transition-transform duration-700 group-hover:scale-110">

                                        <div class="absolute inset-0 bg-brown/20 blur-[30px] rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>

                                        <img
                                            src="{{ $category->featuredImageUrl }}"
                                            alt="{{ $category->title }}"
                                            class="relative z-10 w-40 h-40 md:w-52 md:h-52 object-contain drop-shadow-2xl"
                                        >

                                    </div>

                                </div>

                                <span class="text-[13px] font-black text-gray-500 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors duration-300">
            {{ $category->title }}
        </span>

                                <div class="mt-4 w-1.5 h-1.5 bg-brown rounded-full opacity-0 group-hover:opacity-100 group-hover:scale-[3] transition-all duration-500"></div>

                            </a>

                        @endif
                    @endforeach
                </div>
                @elseif((int) $data['view'] == 2)
                    <section class="container w-full mb-32 px-6" dir="rtl">

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            @foreach($categories ?? [] as $category)
                                <div class="group relative min-h-[400px] overflow-hidden rounded-[4rem] border border-white/60 dark:border-none shadow-lg shadow-brown-500/5">
                                    <div class="absolute inset-0 bg-white/40 dark:bg-slate-900/60 backdrop-blur-[40px] z-0 dark:hidden"></div>
                                    @php
                                        $color = $colors[array_rand($colors)];
                                    @endphp

                                    @if ($loop->odd)

                                        <div class="absolute -top-20 -right-20 w-64 h-64
            bg-{{ $color }}-600/10
            dark:bg-{{ $color }}-400/10
            rounded-full blur-md
            group-hover:scale-110 transition-transform duration-1000">
                                        </div>

                                    @else

                                        <div class="absolute -bottom-20 -left-20 w-64 h-64
            bg-{{ $color }}-600/10
            dark:bg-{{ $color }}-400/10
            rounded-full blur-md
            group-hover:scale-110 transition-transform duration-1000">
                                        </div>

                                    @endif
                                    <div class="relative z-10 h-full p-12 flex flex-col justify-between">
                                        <div class="max-w-xs space-y-6">
                                            @foreach($category->tags ?? []  as $tag)
                                                <span class="inline-block px-4 py-2 bg-brown-600/10 dark:bg-brown-400/20 text-brown-600 dark:text-brown-400 rounded-2xl text-[10px] font-black uppercase tracking-widest">{{$tag->title}}</span>

                                            @endforeach
                                            <h2 class="text-4xl font-black text-gray-900 dark:text-white leading-tight">{{$category->title}}</h2>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 leading-8">{{$category->short_description}}</p>

                                            <a href="#" class="inline-flex items-center gap-3 text-gray-900 dark:text-white font-black text-sm group-hover:gap-5 transition-all">
                                                مشاهده محصولات
                                                <svg class="w-5 h-5 bg-brown-600 text-white rounded-full p-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"></path></svg>
                                            </a>
                                        </div>

                                        <div class="absolute -left-10 bottom-3 w-1/2 transform translate-y-10 group-hover:translate-y-0 group-hover:-rotate-6 transition-all duration-700 pointer-events-none">
                                            <img src="{{$category->featuredImageUrl}}" class="w-full h-auto drop-shadow-[0_20px_40px_rgba(0,0,0,0.2)]" alt="Work Category">
                                        </div>
                                    </div>
                                </div>

                            @endforeach
                        </div>
                    </section>
                @endif
            @endif


        </div>
    </section>

</div>
