<?php

use Livewire\Component;

new class extends Component
{
    public $campaign;
    public $data;
    public $products;
    public function mount($data)
    {
        $this->data = $data;
        $this->campaign = \App\Models\Campaign::active()
            ->where('id', $data['campaign_id'])
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now())
            ->with('targets')
            ->first();

        if (!$this->campaign) {
            return;
        }

        $this->products = $this->campaign
            ->targetProducts()
            ->take($data['limit'] ?? 8)
            ->get();
    }
};
?>

<div>
    @if($campaign)
        <section class="overflow-hidden dark:bg-[#030712] transition-colors duration-500">
        <div class="container">
            <div class="relative bg-white/40 dark:bg-white/[0.02] backdrop-blur-md rounded-[4rem] p-2 overflow-hidden border border-white/80 dark:border-white/10 shadow-lg shadow-secondary-500/5">

                <div class="absolute top-0 left-0 w-full h-full pointer-events-none overflow-hidden rounded-[4rem]">
                    <div class="absolute -top-1/4 -right-1/4 w-[60%] h-[60%] bg-secondary-500/10 dark:bg-secondary-500/20 rounded-full blur-[120px] animate-pulse"></div>
                    <div class="absolute -bottom-1/4 -left-1/4 w-[60%] h-[60%] bg-blue-500/10 dark:bg-blue-600/10 rounded-full blur-[120px]"></div>
                </div>

                <div class="relative z-10 flex flex-col lg:flex-row items-stretch">

                    <div class="w-full lg:w-1/3 xl:w-1/4 p-10 flex flex-col justify-between border-b lg:border-b-0 lg:border-l border-gray-100 dark:border-white/5">
                        <div>
                            <div class="flex items-center gap-3 mb-8">
                            <span class="flex h-4 w-4 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-4 w-4 bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]"></span>
                            </span>
                                <span class="text-red-500 dark:text-red-400 text-[11px] font-black uppercase tracking-[0.3em]">Special Offers</span>
                            </div>

                            <h2 class="text-5xl font-black text-gray-900 dark:text-white leading-[1.2] mb-6">
                                @php
                                    $words = preg_split('/\s+/', trim($this->data['title']));
                                    $lastWord = array_pop($words);
                                    $firstPart = implode(' ', $words);
                                @endphp

                                {{ $firstPart }}

                                @if($firstPart)
                                    <br>
                                @endif

                                <span class="relative">
                                    <span class="relative z-10 text-transparent bg-clip-text bg-gradient-to-l from-red-400 to-red-600">
                                        {{ $lastWord }}
                                    </span>
                                </span>
                                <span class="absolute bottom-2 right-0 w-full h-3 bg-secondary-500/20 -rotate-2"></span>
                            </h2>
                            <p class="text-gray-500 dark:text-gray-400 text-sm leading-8 mb-10 font-medium">
                                {!! $campaign->description !!}
                            </p>
                        </div>
                        <div class="bg-black/5 dark:bg-white/5 backdrop-blur-md rounded-[2.5rem] p-8 border border-black/5 dark:border-white/10 shadow-inner" dir="ltr">
                            <div class="flex flex-row-reverse justify-between items-center text-center">
                                <div class="timer-block incredible-timer-container"  data-end-time="{{ $campaign->end_at_timestamp }}"  >
                                    <span id="seconds" class="text-3xl font-black text-gray-900 dark:text-secondary-400 tracking-tighter">۰۰</span>
                                    <span class="text-[8px] text-gray-400 font-black uppercase mt-1">ثانیه</span>
                                </div>
                                <span class="text-secondary-500 font-black animate-pulse text-2xl mb-4">:</span>
                                <div class="timer-block">
                                    <span id="minutes" class="text-3xl font-black text-gray-900 dark:text-secondary-400 tracking-tighter">۰۰</span>
                                    <span class="text-[8px] text-gray-400 font-black uppercase mt-1">دقیقه</span>
                                </div>
                                <span class="text-secondary-500 font-black animate-pulse text-2xl mb-4">:</span>
                                <div class="timer-block">
                                    <span id="hours" class="text-3xl font-black text-gray-900 dark:text-secondary-400 tracking-tighter">۰۰</span>
                                    <span class="text-[8px] text-gray-400 font-black uppercase mt-1">ساعت</span>
                                </div>
                            </div>
                        </div>

                        <a href="#" class="mt-10 flex items-center justify-center gap-2 py-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-[2rem] font-black text-sm hover:scale-105 transition-all shadow-xl">
                            <span>مشاهده همه</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>

                    <div class="w-full lg:w-2/3 xl:w-3/4 p-8 lg:p-14 overflow-hidden">
                        <div class="swiper incredibleSwiper !overflow-visible">
                            <div class="swiper-wrapper">
                                @foreach($products ?? [] as $product)
                                    @php
                                        $discount = 0;

                                        if(
                                            $product?->displayVariant?->compare_price >
                                            $product?->displayVariant?->price
                                        ){
                                            $discount = round(
                                                (
                                                    ($product->displayVariant->compare_price -
                                                    $product->displayVariant->price)
                                                    /
                                                    $product->displayVariant->compare_price
                                                ) * 100
                                            );
                                        }
                                    @endphp

                                    <div class="swiper-slide h-auto !w-[280px] md:!w-[320px] lg:!w-[350px]">
                                        <div class="group relative h-full pt-10">
                                            <div class="absolute inset-0 bg-white/60 dark:bg-white/[0.03] backdrop-blur-md rounded-[3.5rem] border border-white dark:border-white/10 shadow-lg transition-all duration-300 group-hover:border-secondary-400/50 group-hover:shadow-secondary-500/10"></div>

                                            <div class="relative p-8 flex flex-col h-full z-10 transition-all duration-300 group-hover:-translate-y-2">
                                                @if($discount > 0)
                                                    <div class="absolute -top-4 -right-2 z-20">
                                                        <div class="bg-gradient-to-br from-secondary-400 to-secondary-600 text-white text-[11px] font-black w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg shadow-secondary-500/40 rotate-12 group-hover:rotate-3 transition-all duration-300">
                                                            {{ $discount }}٪-

                                                        </div>
                                                    </div>
                                                @endif
                                                <div class="relative mb-8 flex items-center justify-center">
                                                    <img src="{{$product->featuredImageUrl}}"
                                                         class="relative z-10 w-full h-48 object-contain transition-all
                                                          duration-300 group-hover:scale-105" alt="{{$product->title}}">

                                                    <div class="absolute top-0 left-0 z-20 flex flex-col gap-3 opacity-0 group-hover:opacity-100 -translate-x-2 group-hover:translate-x-0 transition-all duration-300">

                                                        <div class="relative flex items-center group/tooltip">
                                                            <button data-id="123" class="quick-view-btn w-10 h-10 bg-white/90 dark:bg-gray-900/90 backdrop-blur-md text-gray-900 dark:text-white rounded-xl flex items-center justify-center shadow-sm border border-white dark:border-gray-700 hover:bg-secondary-500 dark:hover:bg-secondary-500 hover:text-white transition-all duration-200">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                                </svg>
                                                            </button>
                                                            <span class="absolute right-full mr-3 whitespace-nowrap bg-gray-900 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 pointer-events-none translate-x-2 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-x-0 transition-all duration-200 after:content-[''] after:absolute after:top-1/2 after:-translate-y-1/2 after:-right-1 after:border-4 after:border-transparent after:border-l-gray-900">
                                                            مشاهده سریع
                                                        </span>
                                                        </div>

                                                        <div class="relative flex items-center group/tooltip">
                                                            <button class="w-10 h-10 bg-white/90 dark:bg-gray-900/90 backdrop-blur-md text-gray-900 dark:text-white rounded-xl flex items-center justify-center shadow-sm border border-white dark:border-gray-700 hover:text-red-500 transition-all duration-200">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                                                </svg>
                                                            </button>
                                                            <span class="absolute right-full mr-3 whitespace-nowrap bg-gray-900 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 pointer-events-none translate-x-2 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-x-0 transition-all duration-200 after:content-[''] after:absolute after:top-1/2 after:-translate-y-1/2 after:-right-1 after:border-4 after:border-transparent after:border-l-gray-900">
                                                            افزودن به علاقه‌مندی
                                                        </span>
                                                        </div>

                                                    </div>

                                                </div>

                                                <h3 class="text-sm font-bold text-gray-800 dark:text-white mb-6 line-clamp-2 leading-7 h-14 group-hover:text-secondary-600 transition-colors duration-200">
                                                    {{$product->title}}
                                                </h3>

                                                <div class="flex items-center justify-between mt-auto pt-6 border-t border-black/5 dark:border-white/5">
                                                    <div class="flex flex-col">
                                                        <span class="text-[10px] text-gray-400 line-through">{{number_format($product?->displayVariant?->compare_price)}}</span>
                                                        <div class="flex items-center gap-1">
                                                            <span class="text-xl font-black text-gray-900 dark:text-white tracking-tighter">{{number_format($product?->displayVariant?->price)}}</span>
                                                            <span class="text-[10px] text-gray-500 font-bold">تومان</span>
                                                        </div>
                                                    </div>
                                                    <button class="w-14 h-14 bg-secondary-600 dark:bg-secondary-500 text-white rounded-[1.2rem] flex items-center justify-center shadow-lg hover:scale-105 transition-all duration-200 group/btn relative overflow-hidden">
                                                        <svg class="w-6 h-6 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover/btn:animate-[shimmer_1.5s_infinite]"></div>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                @endforeach
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
    @endif
</div>
