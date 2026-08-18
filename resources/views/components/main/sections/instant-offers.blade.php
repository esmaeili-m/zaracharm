<?php

use Livewire\Component;
use App\Models\ProductVariant;
new class extends Component
{
    public  $products;
    public  $data ;


    public function mount($data)
    {
        $this->data = $data;

        $this->loadProductVariants();
    }

    protected function loadProductVariants(): void
    {
        $limit = (int) ($this->data['limit'] ?? 8);
        $cacheKey = 'products_' . md5(json_encode($this->data));
        \Illuminate\Support\Facades\Cache::forget($cacheKey);
        $this->products = \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            now()->addMinutes(30),
            function () use ($limit) {

                $query = ProductVariant::with('product')->whereHas('product', function ($q) {
                    $q->active();
                })
                    ->whereIn('id', function ($query) {
                        $query->selectRaw('MIN(id)')
                            ->from('product_variants')
                            ->groupBy('product_id');
                    });

                return match ($this->data['mode']) {

                    'latest' =>
                    $query->latest()
                        ->take($limit)
                        ->get(),

                    'sales' =>
                    $query->withSum('orderItems', 'quantity')
                        ->orderByDesc('order_items_sum_quantity')
                        ->take($limit)
                        ->get(),

                    'views' =>
                    $query->orderByDesc('views')
                        ->take($limit)
                        ->get(),

                    'manual' =>
                    $query->whereIn(
                        'id',
                        $this->data['product_ids'] ?? []
                    )->get(),

                    default =>
                    $query->inRandomOrder()
                        ->take($limit)
                        ->get(),
                };
            }
        );
    }
};
?>

    <div class="xl:col-span-4 relative group bg-white dark:bg-black xl:bg-white/80 xl:dark:bg-[#0a0a0a]/40 xl:backdrop-blur-md rounded-[3rem] p-6 border border-gray-100 dark:border-white/5 flex flex-col transition-all duration-500 shadow-sm">

        <div class="flex justify-between items-center mb-6 relative z-10">
            <div class="flex items-center gap-3">
                <div class="w-2.5 h-2.5 rounded-full bg-primary shadow-[0_0_15px_rgba(var(--color-primary),1)] animate-pulse"></div>
                <h2 class="text-lg font-black text-gray-900 dark:text-white">پیشنهادات لحظه‌ای</h2>
            </div>
            <a href="#" class="text-[10px] font-black text-primary bg-primary/5 dark:bg-primary/10 hover:bg-primary hover:text-white px-4 py-2 rounded-2xl transition-all border border-primary/20">مشاهده همه</a>
        </div>

        <div class="suggestion-wrapper overflow-hidden relative z-10">
            <div class="swiper suggestionSwiper w-full px-1">
                <div class="swiper-wrapper">
                    @foreach($products ?? [] as $product)
                        @php
                            $hasDiscount = $product->compare_price
                                && $product->compare_price > $product->price;

                            $discountPercent = $hasDiscount
                                ? round((($product->compare_price - $product->price) / $product->compare_price) * 100)
                                : 0;
                        @endphp
                    <div class="swiper-slide py-4 px-2">
                        <a href="#" class=" group/card block relative bg-gray-100 dark:bg-[#0c0c0e] p-4 rounded-[2.8rem] border border-gray-100 dark:border-white/5 transition-all duration-500  dark:hover:shadow-[0_20px_40px_-10px_rgba(59,130,246,0.15)] hover:-translate-y-2">

                            <div class="absolute top-6 right-6 z-20">
                                @if($hasDiscount)
                                    <span class="bg-red-500 text-white text-[10px] font-black px-2.5 py-1.5 rounded-full shadow-lg shadow-red-500/30 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M11 15h2v2h-2zm0-8h2v6h-2zm.99-5C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2z"/></svg>
                                             {{ $discountPercent }}٪-
                                        </span>
                                @endif

                            </div>

                            <div class="flex items-center gap-6">
                                <div class="relative w-28 h-28 bg-gray-50 dark:bg-white/5 rounded-[2.2rem] flex-shrink-0 p-3 transition-transform duration-700 group-hover/card:scale-105">
                                    <img src="{{$product->product->featuredImageUrl}}" alt="محصول"
                                         class="w-full h-full object-contain drop-shadow-[0_10px_20px_rgba(0,0,0,0.1)] transition-all duration-700">

                                    <div class="absolute inset-0 bg-primary-500/10 blur-md rounded-full opacity-0 group-hover/card:opacity-100 transition-opacity"></div>
                                </div>

                                <div class="flex flex-col flex-1 gap-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-[9px] font-black px-2 py-0.5 bg-primary/10 text-primary rounded-lg uppercase tracking-tighter">پیشنهاد ویژه</span>
                                    </div>

                                    <h3 class="text-[13px] font-black text-gray-800 dark:text-gray-100 leading-6 truncate group-hover/card:text-primary transition-colors">
                                        {{$product->product->title}}
                                    </h3>

                                    <div class="mt-2 flex flex-col">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] text-gray-400 font-bold line-through decoration-red-400/50 tabular-nums">{{$product->compare_price ? number_format($product->compare_price ?? 0) : ''}}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xl font-black text-gray-900 dark:text-white tabular-nums tracking-tighter">{{number_format($product->price ?? 0)}}</span>
                                            <span class="text-[10px] text-gray-500 dark:text-gray-400 font-bold">تومان</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="absolute bottom-6 left-6 w-10 h-10 bg-primary-500 dark:bg-primary-500 text-white rounded-2xl flex items-center justify-center opacity-0 translate-y-4 group-hover/card:opacity-100 group-hover/card:translate-y-0 transition-all duration-500 shadow-lg shadow-primary-500/20">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2.5" stroke-linecap="round"/></svg>
                                </div>
                            </div>
                        </a>
                    </div>
                    @endforeach


                </div>
            </div>
        </div>

        <div class="mt-auto pt-6 border-t border-gray-100 dark:border-white/10 relative z-10">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4 mr-1">دسترسی سریع</p>
            <div class="grid grid-cols-4 gap-3">
                @foreach($products->take(3) ?? [] as $product)
                    <a href="" class="relative aspect-square bg-gray-50 dark:bg-black rounded-2xl p-2.5 border border-gray-200 dark:border-white/10 transition-all duration-500 group overflow-hidden hover:border-primary/60 dark:hover:shadow-[0_0_15px_rgba(var(--color-primary),0.3)]">
                        <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <img src="{{$product->product->featuredImageUrl}}" class="relative z-10 w-full h-full object-contain group-hover:scale-110 transition-transform duration-500">
                    </a>
                @endforeach

                <button class="aspect-square bg-primary hover:bg-primary-600 rounded-2xl flex flex-col items-center justify-center text-white shadow-[0_10px_20px_rgba(var(--color-primary),0.3)] transition-all active:scale-90 group">
                    <span class="text-xs font-black mb-0.5 group-hover:scale-110 transition-transform">+۱۳</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>
        </div>

        <style>
            .suggestionSwiper{
                height: 200px;
            }
            .suggestionSwiper .swiper-slide{
                height: 355px;
            }
        </style>
    </div>
