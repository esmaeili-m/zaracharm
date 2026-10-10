{{--
    ریل اسلایدری محصولات (سکشن محصولات، حالت «اسلایدر در یک خط»)
    متغیرها: $products، $pictureMode، $wishlistedIds (آرایه شناسه‌ها)
    - اسکرول بومی + snap (کشیدن با انگشت در موبایل)
    - محو شدن لبه‌ها در سمتی که محصول بیشتری هست
    - نوار موقعیت + دکمه قبلی/بعدی (دسکتاپ)؛ با فیلتر دسته‌بندی هم بروز می‌شود
--}}
@php
    $slideClass = 'w-[68%] sm:w-[calc((100%-1rem)/2.2)] md:w-[calc((100%-2.5rem)/3)] lg:w-[calc((100%-3.75rem)/4)] 2xl:w-[calc((100%-5rem)/5)]';
@endphp

<div class="relative"
     x-data="{
        atStart: true, atEnd: false, progress: 0, thumb: 1,
        update() {
            const r = this.$refs.rail; if (!r) return;
            const max = r.scrollWidth - r.clientWidth;
            const x = Math.abs(r.scrollLeft);
            this.atStart = x <= 4;
            this.atEnd = x >= max - 4;
            this.progress = max > 0 ? Math.min(1, x / max) : 0;
            this.thumb = r.scrollWidth > 0 ? Math.min(1, r.clientWidth / r.scrollWidth) : 1;
        },
        step(d) {
            const r = this.$refs.rail;
            const card = r.querySelector('.product-card:not([style*=none])');
            const amount = card ? (card.getBoundingClientRect().width + parseFloat(getComputedStyle(r).columnGap || 0)) * Math.max(1, Math.floor(r.clientWidth / card.getBoundingClientRect().width)) : r.clientWidth * 0.9;
            const rtl = getComputedStyle(r).direction === 'rtl';
            r.scrollBy({ left: d * amount * (rtl ? -1 : 1), behavior: 'smooth' });
        },
        mask() {
            const s = this.atStart ? '#000' : 'transparent';
            const e = this.atEnd ? '#000' : 'transparent';
            const g = `linear-gradient(to left, ${s} 0, #000 48px, #000 calc(100% - 48px), ${e} 100%)`;
            return `-webkit-mask-image:${g};mask-image:${g}`;
        }
     }"
     x-init="
        update();
        new ResizeObserver(() => update()).observe($refs.rail);
        new MutationObserver(() => $nextTick(() => update())).observe($refs.rail, { subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
     ">

    <div id="product-grid" x-ref="rail" x-on:scroll.passive="update()" :style="mask()"
         class="flex gap-4 md:gap-5 overflow-x-auto snap-x snap-mandatory scroll-smooth no-scrollbar py-5 -my-5 px-1 scroll-px-1">
        @foreach($products ?? [] as $product)
            @include('components.main.sections.partials.product-slide-card', [
                'product' => $product,
                'pictureMode' => $pictureMode,
                'wishlisted' => in_array($product->id, $wishlistedIds ?? [], true),
                'slideClass' => $slideClass,
            ])
        @endforeach
    </div>

    {{-- نوار موقعیت + دکمه‌ها (اگر همه محصولات جا شوند نمایش داده نمی‌شود) --}}
    <div class="mt-6 flex items-center gap-4" x-show="!(atStart && atEnd)" x-cloak>
        <div class="relative h-1.5 flex-1 rounded-full bg-gray-200/80 dark:bg-white/10 overflow-hidden" aria-hidden="true">
            <div class="absolute inset-y-0 rounded-full bg-brown-600 transition-all duration-300 ease-out"
                 :style="`width:${Math.max(8, thumb * 100)}%; right:${progress * (100 - Math.max(8, thumb * 100))}%`"></div>
        </div>

        <div class="hidden md:flex items-center gap-2">
            <button type="button" aria-label="قبلی" x-on:click="step(-1)" :disabled="atStart"
                    class="w-11 h-11 rounded-full border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 text-gray-700 dark:text-gray-200 flex items-center justify-center shadow-sm transition-all hover:bg-brown-600 hover:border-brown-600 hover:text-white disabled:opacity-35 disabled:pointer-events-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button type="button" aria-label="بعدی" x-on:click="step(1)" :disabled="atEnd"
                    class="w-11 h-11 rounded-full border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 text-gray-700 dark:text-gray-200 flex items-center justify-center shadow-sm transition-all hover:bg-brown-600 hover:border-brown-600 hover:text-white disabled:opacity-35 disabled:pointer-events-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 19l-7-7 7-7"/></svg>
            </button>
        </div>
    </div>
</div>
