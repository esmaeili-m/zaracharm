{{--
    کارت عمودی محصول برای حالت اسلایدری سکشن محصولات
    متغیرها: $product، $pictureMode، $wishlisted، $slideClass
    کلاس product-card و data-categories برای فیلتر دسته‌بندی (app.js) روی خود اسلاید است.
--}}
@php
    $prices = $product->displayVariant?->priceData() ?? $product->cheapestVariant?->priceData() ?? [];
    $final = (int) ($prices['after_discount'] ?? $prices['price'] ?? 0);
    $hasDiscount = ! empty($prices['has_discount']);
    $image = $product->featuredImageUrl;
@endphp

<article class="product-card group/card snap-start shrink-0 {{ $slideClass }}"
         data-categories="{{ $product->categories->pluck('slug')->implode(' ') }}">
    <div class="relative h-full flex flex-col rounded-[1.75rem] bg-white dark:bg-white/[0.04] border border-gray-100 dark:border-white/10 p-2.5 shadow-[0_6px_24px_-12px_rgba(60,40,20,.18)] transition-all duration-500 hover:-translate-y-1.5 hover:shadow-[0_22px_40px_-18px_rgba(120,72,45,.35)] hover:border-brown-600/20">

        {{-- تصویر --}}
        <a href="{{ route('products.show', $product->slug) }}"
           class="relative block aspect-[4/5] rounded-[1.35rem] overflow-hidden {{ $pictureMode === 'transparent' ? 'bg-gradient-to-br from-gray-50 to-gray-100 dark:from-white/5 dark:to-transparent' : 'bg-gray-100 dark:bg-white/5' }}">
            @if($image)
                <img src="{{ $image }}" alt="{{ $product->title }}" loading="lazy" decoding="async"
                     class="absolute inset-0 w-full h-full {{ $pictureMode === 'transparent' ? 'object-contain p-6 drop-shadow-md' : 'object-cover' }} transition-transform duration-700 ease-out group-hover/card:scale-105">
            @else
                <span class="absolute inset-0 flex items-center justify-center text-gray-300">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
            @endif
            <span class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/25 to-transparent opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></span>

            @if($hasDiscount)
                <span class="absolute top-3 right-3 px-2.5 py-1 rounded-xl bg-red-500 text-white text-[11px] font-black shadow-lg shadow-red-500/30">٪{{ $prices['discount_percent'] ?? '' }}</span>
            @endif
        </a>

        {{-- علاقه‌مندی + مشاهده سریع --}}
        <div class="absolute top-5 left-5 z-10 flex flex-col gap-2">
            <x-main.wishlist-button
                :product-id="$product->id"
                :active="$wishlisted"
                icon-class="w-4 h-4"
                class="w-9 h-9 rounded-xl bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md shadow-sm flex items-center justify-center transition-all"
            />
            <button type="button" aria-label="مشاهده سریع"
                    x-data x-on:click.prevent="$dispatch('zc-quick-view', { id: {{ $product->id }} })"
                    class="w-9 h-9 rounded-xl bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md shadow-sm flex items-center justify-center text-gray-700 dark:text-gray-200 hover:bg-brown-600 hover:text-white transition-all md:opacity-0 md:translate-x-2 md:group-hover/card:opacity-100 md:group-hover/card:translate-x-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
        </div>

        {{-- اطلاعات --}}
        <div class="flex flex-col flex-1 px-1.5 pt-3.5 pb-1">
            <span class="text-[10px] font-bold text-brown-600/80 dark:text-brown-400/80 truncate">{{ $product->brand?->title ?? $product->categories->first()?->title ?? ' ' }}</span>
            <a href="{{ route('products.show', $product->slug) }}"
               class="mt-1 text-[13px] md:text-sm font-black leading-6 text-gray-900 dark:text-white line-clamp-2 min-h-[3rem] hover:text-brown-600 transition-colors">
                {{ $product->title }}
            </a>

            <div class="mt-auto pt-3 flex items-end justify-between gap-2">
                <div class="min-w-0">
                    @if($hasDiscount)
                        <span class="block text-[11px] text-gray-400 line-through tabular-nums leading-4">{{ number_format((int) ($prices['before_discount'] ?? $prices['price'] ?? 0)) }}</span>
                    @endif
                    <span class="block text-base md:text-lg font-black text-gray-900 dark:text-white tabular-nums leading-6">
                        {{ $final ? number_format($final) : '—' }}
                        @if($final)<span class="text-[10px] font-bold text-gray-400">تومان</span>@endif
                    </span>
                </div>
                <a href="{{ route('products.show', $product->slug) }}" aria-label="مشاهده و خرید {{ $product->title }}"
                   class="shrink-0 w-10 h-10 rounded-2xl bg-brown-600 text-white flex items-center justify-center shadow-lg shadow-brown-600/25 hover:bg-brown-700 active:scale-95 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </a>
            </div>
        </div>
    </div>
</article>
