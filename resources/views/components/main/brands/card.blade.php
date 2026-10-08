{{--
    کارت مشترک برند
    استفاده در: صفحه همه برندها (/brands) و سکشن برندها در صفحه‌ساز
    pictureMode: transparent = لوگو بدون پس‌زمینه روی پنل روشن | background = تصویر تمام‌قد کارت
--}}
@props([
    'brand',
    'productsCount' => null,
    'pictureMode' => 'transparent',
])

<a
    href="{{ route('brands.show', $brand->slug) }}"
    {{ $attributes->class('group relative flex flex-col h-full bg-white/70 dark:bg-white/[0.03] backdrop-blur-md rounded-3xl md:rounded-[2.5rem] border border-gray-200 dark:border-white/10 p-1.5 md:p-2 transition-all duration-500 hover:shadow-lg hover:shadow-brown-600/20 md:hover:-translate-y-2') }}
>
    {{-- Logo --}}
    <div class="relative aspect-[4/3] md:aspect-[16/10] rounded-[1.25rem] md:rounded-[2rem] overflow-hidden md:m-1 {{ $pictureMode === 'transparent' ? 'bg-gradient-to-br from-gray-100 to-transparent dark:from-white/5 dark:to-transparent flex items-center justify-center' : 'bg-gray-100 dark:bg-white/5' }}">

        @if($brand->logo_url)
            @if($pictureMode === 'transparent')
                <div class="absolute w-32 h-32 bg-brown-500/20 blur-[60px] rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                <img
                    src="{{ $brand->logo_url }}"
                    alt="{{ $brand->title }}"
                    loading="lazy"
                    class="relative z-10 max-w-[60%] max-h-[65%] object-contain drop-shadow-md transition-transform duration-700 group-hover:scale-110"
                >
            @else
                <img
                    src="{{ $brand->logo_url }}"
                    alt="{{ $brand->title }}"
                    loading="lazy"
                    class="absolute inset-0 w-full h-full object-cover transition-transform duration-1000 ease-out group-hover:scale-110"
                >
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-all duration-500"></div>
            @endif
        @else
            {{-- بدون لوگو: حرف اول نام برند --}}
            <span class="relative z-10 w-14 h-14 md:w-20 md:h-20 rounded-2xl md:rounded-[1.5rem] bg-brown-600/10 border border-brown-600/20 text-brown-600 flex items-center justify-center text-2xl md:text-3xl font-black">
                {{ mb_substr($brand->title, 0, 1) }}
            </span>
        @endif

        @if(!is_null($productsCount))
            <span class="absolute top-2 right-2 md:top-3 md:right-3 z-20 px-2 md:px-3 py-0.5 md:py-1 rounded-lg bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md text-[10px] font-black text-gray-700 dark:text-gray-200 shadow-sm tabular-nums">
                {{ number_format($productsCount) }} محصول
            </span>
        @endif
    </div>

    {{-- Info --}}
    <div class="flex flex-col flex-1 p-2.5 pt-3 md:p-5 md:pt-4">
        <div class="flex items-center justify-between gap-2 md:gap-3 mb-1 md:mb-2 min-w-0">
            <h3 class="min-w-0 truncate font-black text-gray-900 dark:text-white text-[13px] md:text-base leading-tight group-hover:text-brown-600 dark:group-hover:text-brown-400 transition-colors">
                {{ $brand->title }}
            </h3>

            @if($brand->country)
                <span class="hidden sm:inline text-[10px] font-bold text-brown-600 dark:text-brown-400 bg-brown-600/10 px-2.5 py-1 rounded-lg whitespace-nowrap">
                    {{ $brand->country }}
                </span>
            @endif
        </div>

        @if(filled($brand->description))
            <p class="hidden md:block text-[12px] font-medium text-gray-500 dark:text-gray-400 leading-6 line-clamp-2">
                {{ \Illuminate\Support\Str::limit(strip_tags($brand->description), 140) }}
            </p>
        @endif

        <div class="mt-auto pt-2.5 md:pt-4 flex items-center justify-between gap-2 border-t border-gray-100 dark:border-white/5">
            <span class="truncate text-[10px] md:text-[11px] font-black text-gray-500 dark:text-gray-400 group-hover:text-brown-600 dark:group-hover:text-brown-400 transition-colors">
                مشاهده محصولات برند
            </span>
            <span class="shrink-0 w-8 h-8 md:w-10 md:h-10 bg-brown-500 text-white rounded-lg md:rounded-xl flex items-center justify-center shadow-lg shadow-brown-500/20 transition-all duration-500 group-hover:bg-brown-700 group-hover:-translate-x-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </span>
        </div>
    </div>
</a>
