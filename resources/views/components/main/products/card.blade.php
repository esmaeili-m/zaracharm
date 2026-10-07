{{--
    کارت مشترک محصول (طرح Products / View 1)
    استفاده در: سکشن محصولات، سکشن محصولات با فیلتر برند، صفحه برند
    دکمه علاقه‌مندی به toggleWishlist() کامپوننت Livewire والد (HandlesWishlist) وصل است.
    روابط موردنیاز محصول: brand, media(featuredImage), cheapestVariant, specifications
--}}
@props([
    'product',
    'pictureMode' => 'background',
    'wishlisted' => false,
])

@php
    $prices = $product->cheapestVariant?->priceData() ?? [
        'price' => 0,
        'after_discount' => 0,
        'has_discount' => false,
        'discount_percent' => 0,
    ];
@endphp
<div
    {{ $attributes->class('group relative bg-white/70 dark:bg-white/[0.03] backdrop-blur-md rounded-[2.5rem] border border-gray-200 dark:border-white/10 p-2 transition-all duration-500 hover:shadow-lg hover:shadow-brown-600/20 hover:-translate-y-2') }}
>
    <div class="flex h-[220px]">

        {{-- Product Image --}}
        <div class="w-2/5 relative rounded-[2rem] overflow-hidden m-1 transition-all duration-500 group-hover:scale-[0.98]">

            @if($pictureMode === 'background')

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

            {{-- Wishlist --}}
            <x-main.wishlist-button
                :product-id="$product->id"
                :active="$wishlisted"
                icon-class="w-4 h-4"
                class="absolute top-3 left-3 z-20 w-8 h-8 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md rounded-xl flex items-center justify-center shadow-sm transition-all"
            />

        </div>
        {{-- Product Info --}}
        <div class="w-3/5 p-5 flex flex-col justify-between">

            <div>

                <div class="flex justify-between items-start">

<span class="text-[10px] font-bold text-brown-600 dark:text-brown-400 tracking-tighter opacity-80 mb-1 block">
{{ $product->brand?->title ?? 'محصول' }}
</span>

                    <div class="flex gap-1">
                        <div class="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_5px_rgba(120,72,45,0.5)]"></div>
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
