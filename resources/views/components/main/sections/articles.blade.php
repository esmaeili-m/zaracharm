<?php

use Livewire\Component;
use App\Models\Article;
new class extends Component
{
    public $articles;
    public $data;
    public $view;
    public $categories=[];
    public function mount($data)
    {
        $this->data=$data;
        if (!$this->data){
            return;
        }
        $this->loadArticles();
        $this->view = match ((int) ($data['view'] ?? 1)) {
            1 => 'جدیدترین ',
            2 => 'پرفروش‌ترین ',
            3 => 'پربازدیدترین ',
            4 => 'پیشنهادی‌ترین ',
            5 => 'منتخب ',
            default => 'جدیدترین ',
        };
    }
    protected function loadArticles()
    {
        $limit = $this->data['limit'] ?? 8;

        $this->articles = match ($this->data['mode']) {

            'latest' => Article::query()->with('tags')
                ->latest()
                ->limit($limit)
                ->get(),

            'views' => Article::query()->with('tags')
                ->orderByDesc('views')
                ->limit($limit)
                ->get(),

            'random' => Article::query()->with('tags')
                ->inRandomOrder()
                ->limit($limit)
                ->get(),

            'manual' => Article::query()->with('tags')
                ->whereIn(
                    'id',
                    $this->data['article_ids'] ?? []
                )
                ->get(),

            default => collect()
        };
    }
};
?>



<section class="magazine-section relative overflow-hidden py-16 transition-colors duration-700">
    <div class="container relative z-10">

        <div class="flex items-end justify-between mb-10 gap-4 flex-wrap">
            <div class="flex items-center gap-6">
                <div class="relative group">
                    <div class="relative w-16 h-16 bg-white dark:bg-black border border-gray-100 dark:border-brown-500/40 rounded-[1.8rem] flex items-center justify-center text-brown-600 shadow-2xl">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <div>
                    <h2 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{$data['title'] ?? 'مقالات زارا چرم'}}</h2>
                    <p class="text-[10px] font-black text-brown-500 uppercase tracking-[0.4em] mt-2 flex items-center gap-2">
                        <span class="w-8 h-[2px] bg-brown-500/30"></span>
                        Latest News
                    </p>
                </div>
            </div>

            <a href="#" class="group/link relative overflow-hidden px-8 py-3.5 rounded-2xl transition-all duration-500 flex items-center gap-3 bg-white/40 backdrop-blur-md border border-gray-200 text-gray-800 hover:border-brown-500/50 hover:text-white dark:bg-white/[0.03] dark:border-white/10 dark:text-gray-300 dark:hover:text-white">
                <span class="absolute inset-0 bg-brown-600 translate-y-full group-hover/link:translate-y-0 transition-transform duration-500 ease-out"></span>
                <span class="relative z-10 text-[13px] font-black tracking-tight">مشاهده همه مطالب</span>
                <div class="relative z-10 w-5 h-5 flex items-center justify-center bg-brown-600/10 dark:bg-white/5 rounded-lg group-hover/link:bg-white/20 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </div>
            </a>
        </div>

        <div class="swiper magazineSwiper !overflow-visible swiper-initialized swiper-horizontal swiper-rtl swiper-backface-hidden">
            <div class="swiper-wrapper" style="cursor: grab;" id="swiper-wrapper-04898b939d4d421a" aria-live="polite">
                @foreach($articles  ?? [] as $article)
                    <div class="swiper-slide h-auto p-4 swiper-slide-active" style="width: 363px; margin-left: 20px;" role="group" aria-label="1 / 6">
                        <a href="">
                            <div class="group relative h-full pt-10">
                                <div class="absolute inset-0 bg-white/80 dark:bg-[#0a0a0a]/40 backdrop-blur-md rounded-[2.8rem] border border-gray-100 dark:border-white/5 shadow-sm transition-all duration-500 group-hover:border-brown-400/40 group-hover:shadow-brown-500/15"></div>

                                <div class="relative p-5 flex flex-col h-full z-10 transition-transform duration-500 group-hover:-translate-y-4">
                                    <div class="absolute -top-4 -right-2 z-20">
                                        @foreach($article->tags ?? [] as $tag)
                                            <div class="bg-brown-600 mb-1 text-white text-[10px] font-black px-4 py-2 rounded-xl shadow-lg shadow-brown-500/30">{{$tag->title}}</div>

                                        @endforeach
                                    </div>

                                    <div class="relative mb-6 overflow-hidden rounded-[2rem] h-48 shadow-lg">
                                        <img src="{{$article->featuredImageUrl}}" class="w-full h-full object-cover transition-all duration-1000 group-hover:scale-110" alt="Blog Title">
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    </div>

                                    <div class="flex items-center gap-4 mb-4 text-[11px] font-bold text-gray-400">
                                        <span class="flex items-center gap-1.5"><i class="far fa-clock text-brown-500"></i> {{ $article->reading_time }} دقیقه مطالعه </span>
                                        <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                                        <span class="tabular-nums">{{$article->published_at}}</span>
                                    </div>

                                    <h3 class="text-[16px] font-black text-gray-800 dark:text-gray-100 mb-6 line-clamp-2 leading-7 h-14 group-hover:text-brown-600 transition-colors">
                                        {{$article->title}}
                                    </h3>

                                    <div class="flex items-center justify-between mt-auto pt-5 border-t border-gray-100 dark:border-white/5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-brown-100 dark:bg-brown-900/30 flex items-center justify-center text-brown-600 font-black text-[10px]">{{ mb_substr($article?->author?->name, 0, 1) }}</div>
                                            <span class="text-[12px] font-bold text-gray-600 dark:text-gray-400">{{$article?->author->name}}</span>
                                        </div>
                                        <div class="w-10 h-10 bg-brown-500 dark:bg-brown-600 text-white rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-all">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"></path></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                @endforeach


            </div>
            <span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span></div>

        <div class="flex justify-center gap-6 mt-12">
            <div class="swiper-nav-prev w-14 h-14 rounded-2xl bg-white/50 dark:bg-white/5 dark:text-white border border-white dark:border-white/10 flex items-center justify-center cursor-pointer hover:bg-brown-600 hover:text-white transition-all shadow-lg group swiper-button-disabled" tabindex="-1" role="button" aria-label="Previous slide" aria-controls="swiper-wrapper-04898b939d4d421a" aria-disabled="true">
                <svg class="w-6 h-6 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
            </div>
            <div class="swiper-nav-next w-14 h-14 rounded-2xl bg-white/50 dark:bg-white/5 dark:text-white border border-white dark:border-white/10 flex items-center justify-center cursor-pointer hover:bg-brown-600 hover:text-white transition-all shadow-lg group" tabindex="0" role="button" aria-label="Next slide" aria-controls="swiper-wrapper-04898b939d4d421a" aria-disabled="false">
                <svg class="w-6 h-6 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
            </div>
        </div>
    </div>
</section>
