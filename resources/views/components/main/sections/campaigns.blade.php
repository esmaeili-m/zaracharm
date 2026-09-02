<?php

use Livewire\Component;
use App\Enums\CampaignType;

new class extends Component
{
    public $campaign;
    public $data;
    public $products;
    public $campaignType;

    // فرض: تو پنل ادمین برای هر کمپین یک استایل تصویر مشخص می‌کنی
    // و همون مقدار داخل $data میاد. مقادیر مجاز: 'transparent' | 'background'
    // اگه اسم کلید یا مقدارهاش تو دیتای خودت فرق داره فقط همینجا رو عوض کن.
    public $imageStyle = 'transparent';
    public $view = 1;

    public function mount($data)
    {
        $this->data = $data;
        if (!$data){
            return;
        }
        $this->campaign = \App\Models\Campaign::active()
            ->where('id', $data['campaign_id'])
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now())
            ->with('targets')
            ->first();

        if (!$this->campaign) {
            return;
        }

        $this->campaignType = CampaignType::tryFrom($this->campaign->getRawOriginal('type'));
        $this->imageStyle = $data['image_style'] ?? 'transparent';

        $this->products = $this->campaign
            ->targetProducts()
            ->take($data['limit'] ?? 8)
            ->get();
    }

    /**
     * بر اساس نوع کمپین مشخص می‌کنه این کمپین اصلا برای چیه (متن + رنگ بج)
     */
    public function getCampaignBadgeProperty(): array
    {
        return match ($this->campaignType) {
            CampaignType::Discount => [
                'label' => 'تخفیف ویژه',
                'dot'   => 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]',
                'ping'  => 'bg-red-400',
                'text'  => 'text-red-500 dark:text-red-400',
            ],
            CampaignType::FlashSale => [
                'label' => 'فروش فوق‌العاده',
                'dot'   => 'bg-orange-500 shadow-[0_0_10px_rgba(249,115,22,0.5)]',
                'ping'  => 'bg-orange-400',
                'text'  => 'text-orange-500 dark:text-orange-400',
            ],
            CampaignType::FreeShipping => [
                'label' => 'ارسال رایگان',
                'dot'   => 'bg-brown-500 shadow-[0_0_10px_rgba(59,130,246,0.5)]',
                'ping'  => 'bg-brown-400',
                'text'  => 'text-brown-500 dark:text-brown-400',
            ],
            CampaignType::Gift => [
                'label' => 'هدیه ویژه',
                'dot'   => 'bg-pink-500 shadow-[0_0_10px_rgba(236,72,153,0.5)]',
                'ping'  => 'bg-pink-400',
                'text'  => 'text-pink-500 dark:text-pink-400',
            ],
            CampaignType::BuyXGetY => [
                'label' => 'بخر و ببر',
                'dot'   => 'bg-purple-500 shadow-[0_0_10px_rgba(168,85,247,0.5)]',
                'ping'  => 'bg-purple-400',
                'text'  => 'text-purple-500 dark:text-purple-400',
            ],
            default => [
                'label' => 'پیشنهاد ویژه',
                'dot'   => 'bg-secondary-500',
                'ping'  => 'bg-secondary-400',
                'text'  => 'text-secondary-500 dark:text-secondary-400',
            ],
        };
    }
};
?>

<div>
    @if($campaign)
        @if($view == 1)
            <section class="overflow-hidden dark:bg-[#030712] transition-colors duration-500">
                <div class="container">
                    <div class="relative bg-white/40 dark:bg-white/[0.02] backdrop-blur-md rounded-[4rem] p-2 overflow-hidden border border-white/80 dark:border-white/10 shadow-lg shadow-secondary-500/5">

                        <div class="absolute top-0 left-0 w-full h-full pointer-events-none overflow-hidden rounded-[4rem]">
                            <div class="absolute -top-1/4 -right-1/4 w-[60%] h-[60%] bg-secondary-500/10 dark:bg-secondary-500/20 rounded-full blur-[120px] animate-pulse"></div>
                            <div class="absolute -bottom-1/4 -left-1/4 w-[60%] h-[60%] bg-brown-500/10 dark:bg-brown-600/10 rounded-full blur-[120px]"></div>
                        </div>

                        <div class="relative z-10 flex flex-col lg:flex-row items-stretch">

                            <div class="w-full lg:w-1/3 xl:w-1/4 p-10 flex flex-col justify-between border-b lg:border-b-0 lg:border-l border-gray-100 dark:border-white/5">
                                <div>
                                    <div class="flex items-center gap-3 mb-8">
                                <span class="flex h-4 w-4 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $this->campaignBadge['ping'] }} opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-4 w-4 {{ $this->campaignBadge['dot'] }}"></span>
                                </span>
                                        <span class="{{ $this->campaignBadge['text'] }} text-[11px] font-black uppercase tracking-[0.3em]">{{ $this->campaignBadge['label'] }}</span>
                                    </div>
                                    <h2 class="text-5xl font-black text-gray-900 dark:text-white leading-[1.2] mb-6">
                                        @php
                                            $words = preg_split('/\s+/', trim($data['title']));
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
                                                $prices = $product->cheapestVariant?->priceData() ?? [
                                                    'price' => 0,
                                                    'after_discount' => 0,
                                                    'has_discount' => false,
                                                    'discount_percent' => 0,
                                                ];
                                            @endphp

                                            <div class="swiper-slide h-auto !w-[280px] md:!w-[320px] lg:!w-[350px]">
                                                <div class="group relative h-full pt-10">
                                                    <div class="absolute inset-0 bg-white/60 dark:bg-white/[0.03] backdrop-blur-md rounded-[3.5rem] border border-white dark:border-white/10 shadow-lg transition-all duration-300 group-hover:border-secondary-400/50 group-hover:shadow-secondary-500/10"></div>

                                                    <div class="relative p-8 flex flex-col h-full z-10 transition-all duration-300 group-hover:-translate-y-2">
                                                        @if($prices['has_discount'])
                                                            <div class="absolute -top-4 -right-2 z-20">
                                                                <div class="bg-gradient-to-br from-secondary-400 to-secondary-600 text-white text-[11px] font-black w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg shadow-secondary-500/40 rotate-12 group-hover:rotate-3 transition-all duration-300">
                                                                    {{ $prices['discount_percent'] }}٪-
                                                                </div>
                                                            </div>
                                                        @endif

                                                        {{-- تصویر محصول: دو حالت — ترنسپرنت / دارای بکگراند --}}
                                                        <div class="relative mb-8 flex items-center justify-center h-48 {{ $imageStyle === 'transparent' ? '' : 'rounded-[2.5rem] bg-gray-50 dark:bg-white/[0.04] overflow-hidden' }}">
                                                            <img src="{{$product->featuredImageUrl}}"
                                                                 class="relative z-10 transition-all duration-300 group-hover:scale-105 {{ $imageStyle === 'transparent' ? 'max-w-full max-h-full object-contain drop-shadow-xl' : 'w-full h-full object-cover group-hover:scale-110' }}"
                                                                 alt="{{$product->title}}">

                                                            <div class="absolute top-0 left-0 z-20 flex flex-col gap-3 opacity-0 group-hover:opacity-100 -translate-x-2 group-hover:translate-x-0 transition-all duration-300">

                                                                <div class="relative flex items-center group/tooltip">
                                                                    <a href="{{ route('products.show', $product->slug) }}" class="quick-view-btn w-10 h-10 bg-white/90 dark:bg-gray-900/90 backdrop-blur-md text-gray-900 dark:text-white rounded-xl flex items-center justify-center shadow-sm border border-white dark:border-gray-700 hover:bg-secondary-500 dark:hover:bg-secondary-500 hover:text-white transition-all duration-200">
                                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                                        </svg>
                                                                    </a>
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

                                                        <a href="{{ route('products.show', $product->slug) }}" class="text-sm font-bold text-gray-800 dark:text-white mb-6 line-clamp-2 leading-7 h-14 group-hover:text-secondary-600 transition-colors duration-200">
                                                            {{$product->title}}
                                                        </a>

                                                        <div class="flex items-center justify-between mt-auto pt-6 border-t border-black/5 dark:border-white/5">
                                                            <div class="flex flex-col">
                                                                @if($prices['has_discount'])
                                                                    <span class="text-[10px] text-gray-400 line-through">{{ number_format($prices['price']) }}</span>
                                                                @endif
                                                                <div class="flex items-center gap-1">
                                                                <span class="text-xl font-black text-gray-900 dark:text-white tracking-tighter">
                                                                    {{ number_format($prices['has_discount'] ? $prices['after_discount'] : $prices['price']) }}
                                                                </span>
                                                                    <span class="text-[10px] text-gray-500 font-bold">تومان</span>
                                                                </div>
                                                            </div>
                                                            <a href="{{ route('products.show', $product->slug) }}" class="w-14 h-14 bg-secondary-600 dark:bg-secondary-500 text-white rounded-[1.2rem] flex items-center justify-center shadow-lg hover:scale-105 transition-all duration-200 group/btn relative overflow-hidden">
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
                                                                </svg>                                                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover/btn:animate-[shimmer_1.5s_infinite]"></div>
                                                            </a>
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
        @elseif($view == 2)
            <section class="amazing-deals-section relative overflow-hidden transition-colors duration-500">

                <div class="container pb-4 relative z-10">

                    <div class="flex flex-col lg:flex-row items-center justify-between mb-30 gap-8 bg-white/30 dark:bg-white/[0.02] backdrop-blur-md p-8 rounded-[3rem] border border-white/50 dark:border-white/10 shadow-xl">
                        <div class="flex items-center gap-5">
                            <div class="relative">
                                <div class="absolute inset-0 {{ $this->campaignBadge['dot'] }} blur-md opacity-40 animate-ping"></div>
                                <div class="relative w-4 h-12 {{ $this->campaignBadge['dot'] }} rounded-full"></div>
                            </div>
                            <div>
                                @php
                                    $words2 = preg_split('/\s+/', trim($this->data['title']));
                                    $lastWord2 = array_pop($words2);
                                    $firstPart2 = implode(' ', $words2);
                                @endphp
                                <h2 class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">
                                    {{ $firstPart2 }}
                                    <span class="{{ $this->campaignBadge['text'] }}">{{ $lastWord2 }}</span>
                                </h2>
                                <p class="text-gray-500 dark:text-gray-400 font-medium mt-1">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($campaign->description), 60) }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 bg-black/5 dark:bg-white/5 p-3 rounded-2xl border border-black/5 dark:border-white/5">
                            <div class="flex gap-3 text-2xl font-black dark:text-white amazing-timer-container" data-end-time="{{ $campaign->end_at_timestamp }}">
                                <div class="timer-box flex flex-col items-center">
                                    <span data-timer-unit="seconds" class="{{ $this->campaignBadge['dot'] }} text-white px-3 py-1 rounded-xl shadow-lg">۰۰</span>
                                    <span class="text-[10px] mt-1 opacity-50">ثانیه</span>
                                </div>
                                <span class="mt-1 {{ $this->campaignBadge['text'] }}">:</span>
                                <div class="timer-box flex flex-col items-center">
                                    <span data-timer-unit="minutes">۰۰</span>
                                    <span class="text-[10px] mt-1 opacity-50">دقیقه</span>
                                </div>
                                <span class="mt-1 opacity-20">:</span>
                                <div class="timer-box flex flex-col items-center">
                                    <span data-timer-unit="hours">۰۰</span>
                                    <span class="text-[10px] mt-1 opacity-50">ساعت</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="swiper main-amazing-swiper !overflow-visible">
                        <div class="swiper-wrapper">

                            @foreach($products ?? [] as $product)
                                @php
                                    $prices2 = $product->cheapestVariant?->priceData() ?? [
                                        'price' => 0,
                                        'after_discount' => 0,
                                        'has_discount' => false,
                                        'discount_percent' => 0,
                                    ];
                                @endphp

                                <div class="swiper-slide">
                                    <div class="group relative amazing-card-wrapper h-full">
                                        <div class="absolute inset-0 bg-white/40 dark:bg-white/[0.03] backdrop-blur-md rounded-[3rem] border border-white/60 dark:border-white/10 shadow-lg transition-all duration-500 group-hover:shadow-brown-500/20 group-hover:-translate-y-3"></div>
                                        <div class="relative p-8 flex flex-col items-center text-center h-full">

                                            <div class="absolute top-0 right-10 z-20 flex flex-col gap-3 opacity-0 group-hover:opacity-100 -translate-x-4 group-hover:translate-x-0 transition-all duration-500">

                                                <div class="relative flex items-center group/tooltip">
                                                    <button data-id="{{ $product->id }}" class="quick-view-btn w-10 h-10 bg-white/90 dark:bg-gray-900/90 backdrop-blur-md text-gray-900 dark:text-white rounded-xl flex items-center justify-center shadow-sm border border-white dark:border-gray-700 hover:bg-secondary-500 dark:hover:bg-secondary-500 hover:text-white transition-all">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                        </svg>
                                                    </button>
                                                    <span class="absolute right-full mr-3 whitespace-nowrap bg-gray-900 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 pointer-events-none translate-x-2 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-x-0 transition-all duration-300 after:content-[''] after:absolute after:top-1/2 after:-translate-y-1/2 after:-right-1 after:border-4 after:border-transparent after:border-l-gray-900">
                                                        مشاهده سریع
                                                    </span>
                                                </div>

                                                <div class="relative flex items-center group/tooltip">
                                                    <button class="w-10 h-10 bg-white/90 dark:bg-gray-900/90 backdrop-blur-md text-gray-900 dark:text-white rounded-xl flex items-center justify-center shadow-sm border border-white dark:border-gray-700 hover:text-red-500 transition-all">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                                        </svg>
                                                    </button>
                                                    <span class="absolute right-full mr-3 whitespace-nowrap bg-gray-900 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 pointer-events-none translate-x-2 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-x-0 transition-all duration-300 after:content-[''] after:absolute after:top-1/2 after:-translate-y-1/2 after:-right-1 after:border-4 after:border-transparent after:border-l-gray-900">
                                                        افزودن به علاقه‌مندی
                                                    </span>
                                                </div>

                                            </div>

                                            <div class="relative -mt-24 mb-6 transition-transform duration-700 group-hover:scale-110">
                                                <div class="absolute inset-0 bg-brown-500/20 blur-[60px] rounded-full scale-75"></div>
                                                <img src="{{ $product->featuredImageUrl }}" class="w-40 drop-shadow-[0_20px_40px_rgba(0,0,0,0.2)] relative z-10" alt="{{ $product->title }}">
                                            </div>

                                            @if($prices2['has_discount'])
                                                <div class="absolute top-8 left-8 {{ $this->campaignBadge['dot'] }} text-white text-[10px] font-black px-3 py-1.5 rounded-full shadow-lg transform -rotate-12">
                                                    ٪{{ $prices2['discount_percent'] }}-
                                                </div>
                                            @endif

                                            <h3 class="text-xl font-black text-gray-800 dark:text-white mb-1">{{ $product->title }}</h3>
                                            <p class="text-[11px] text-gray-400 mb-6 font-medium">{{ $product->brand?->title }}</p>

                                            <div class="w-full mt-auto pt-6 border-t border-black/5 dark:border-white/5">
                                                <div class="flex flex-col mb-4 text-right">
                                                    @if($prices2['has_discount'])
                                                        <span class="text-xs text-gray-400 line-through font-bold">{{ number_format($prices2['price']) }}</span>
                                                    @endif
                                                    <span class="text-2xl font-black text-brown-600 dark:text-brown-400">
                                                        {{ number_format($prices2['has_discount'] ? $prices2['after_discount'] : $prices2['price']) }}
                                                        <span class="text-[10px] opacity-60">تومان</span>
                                                    </span>
                                                </div>
                                                <button class="relative overflow-hidden w-full py-4 bg-brown-500 dark:bg-brown-600 text-white rounded-2xl font-black shadow-lg transition-all duration-500 group/btn">
                                                    <span class="relative z-10">افزودن به سبد</span>
                                                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover/btn:animate-[shimmer_1.5s_infinite]"></div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                        </div>

                        <div class="flex justify-center gap-4 mt-10">
                            <div class="swiper-nav-prev w-14 h-14 rounded-2xl bg-white/50 dark:bg-white/5 dark:text-white border border-white dark:border-white/10 flex items-center justify-center cursor-pointer hover:bg-brown-600 hover:text-white transition-all shadow-lg group">
                                <svg class="w-6 h-6 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                            </div>
                            <div class="swiper-nav-next w-14 h-14 rounded-2xl bg-white/50 dark:bg-white/5 dark:text-white border border-white dark:border-white/10 flex items-center justify-center cursor-pointer hover:bg-brown-600 hover:text-white transition-all shadow-lg group">
                                <svg class="w-6 h-6 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    @endif
</div>
