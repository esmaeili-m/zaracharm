<?php

use App\Support\Sections\Banners;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * سکشن بنرها (صفحه‌ساز) — توضیح data در App\Support\Sections\Banners
 * ستون‌ها/فاصله/نسبت با CSS متغیر و style محلی ساخته می‌شوند (بدون کلاس پویای Tailwind)
 */
new class extends Component
{
    public ?array $data = []; // سکشن تازه‌اضافه‌شده (بدون تنظیمات) data = null دارد
    public array $banners = [];
    public string $uid;

    public function mount($data = [], $media = null)
    {
        $this->data = is_array($data) ? $data : [];
        $this->uid = 'zc-bn-' . Str::random(6);

        $media = collect($media ?? []);
        $url = fn ($m) => $m ? ($m->external_url ?: url('/storage/' . $m->file_path)) : null;

        foreach (array_values($this->data['items'] ?? []) as $item) {
            $uid = (string) ($item['uid'] ?? '');
            $desktop = $media->first(fn ($m) => $m->collection === Banners::DESKTOP && $m->purpose === $uid);

            // بنر بدون تصویر نمایش داده نمی‌شود
            if (!$desktop) {
                continue;
            }

            $mobile = $media->first(fn ($m) => $m->collection === Banners::MOBILE && $m->purpose === $uid);

            $this->banners[] = [
                'desktop' => $url($desktop),
                'mobile' => $url($mobile),
                'title' => trim((string) ($item['title'] ?? '')),
                'subtitle' => trim((string) ($item['subtitle'] ?? '')),
                'link' => $this->safeLink($item['link'] ?? null),
                'new_tab' => (bool) ($item['new_tab'] ?? false),
                'alt' => trim((string) ($item['alt'] ?? '')) ?: trim((string) ($item['title'] ?? '')) ?: 'بنر',
            ];
        }
    }

    /** لینک امن: فقط http(s)، مسیر داخلی یا لنگر */
    public function safeLink(?string $link): ?string
    {
        $link = trim((string) $link);

        return $link !== '' && preg_match('#^(https?://|/|\#)#i', $link) ? $link : null;
    }
};
?>

@php
    $slider = ($data['layout'] ?? 'grid') === 'slider' && count($banners) > 1;
    $colsDesktop = max(1, min(4, (int) ($data['columns_desktop'] ?? 2)));
    $colsMobile = max(1, min(2, (int) ($data['columns_mobile'] ?? 1)));
    $gap = ['sm' => '8px', 'md' => '16px', 'lg' => '28px'][$data['gap'] ?? 'md'] ?? '16px';
    $ratio = array_key_exists($data['ratio'] ?? 'auto', \App\Support\Sections\Banners::RATIOS) ? ($data['ratio'] ?? 'auto') : 'auto';
    $rounded = (bool) ($data['rounded'] ?? true);
    $zoom = (bool) ($data['hover_zoom'] ?? true);
    $showText = (bool) ($data['show_text'] ?? true);
    $autoplay = $slider && (bool) ($data['autoplay'] ?? true);
@endphp

<div>
    @if($banners)
        <section id="{{ $uid }}" dir="rtl" class="relative"
                 style="--bn-m: {{ $colsMobile }}; --bn-d: {{ min($colsDesktop, count($banners)) }}; --bn-g: {{ $gap }};"
                 @if($slider)
                     x-data="{
                        active: 0,
                        count: {{ count($banners) }},
                        timer: null,
                        slideWidth() { const s = this.$refs.track.firstElementChild; return s ? s.offsetWidth + parseFloat(getComputedStyle(this.$refs.track).columnGap || 0) : 0 },
                        go(i) {
                            this.active = (i + this.count) % this.count;
                            {{-- RTL: پیمایش به سمت چپ منفی است --}}
                            this.$refs.track.scrollTo({ left: -this.active * this.slideWidth(), behavior: 'smooth' });
                        },
                        sync() { const w = this.slideWidth(); if (w) this.active = Math.min(this.count - 1, Math.round(Math.abs(this.$refs.track.scrollLeft) / w)) },
                        atEnd() { const t = this.$refs.track; return Math.abs(t.scrollLeft) + t.clientWidth >= t.scrollWidth - 4 },
                        next() { this.atEnd() ? this.go(0) : this.go(this.active + 1) },
                        start() { @if($autoplay) this.stop(); if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) this.timer = setInterval(() => this.next(), 5000) @endif },
                        stop() { clearInterval(this.timer) },
                     }"
                     x-init="start()"
                     @mouseenter="stop()" @mouseleave="start()" @focusin="stop()"
                 @endif
        >
            <style>
                #{{ $uid }} .zc-bn-grid { display: grid; gap: var(--bn-g); grid-template-columns: repeat(var(--bn-m), minmax(0, 1fr)); }
                #{{ $uid }} .zc-bn-track { display: flex; gap: var(--bn-g); overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; overscroll-behavior-x: contain; }
                #{{ $uid }} .zc-bn-track::-webkit-scrollbar { display: none; }
                #{{ $uid }} .zc-bn-track > * { flex: 0 0 calc((100% - (var(--bn-m) - 1) * var(--bn-g)) / var(--bn-m)); scroll-snap-align: start; }
                @media (min-width: 768px) {
                    #{{ $uid }} .zc-bn-grid { grid-template-columns: repeat(var(--bn-d), minmax(0, 1fr)); }
                    #{{ $uid }} .zc-bn-track > * { flex-basis: calc((100% - (var(--bn-d) - 1) * var(--bn-g)) / var(--bn-d)); }
                }
            </style>

            <div class="{{ $slider ? 'zc-bn-track' : 'zc-bn-grid' }}" @if($slider) x-ref="track" @scroll.debounce.100ms="sync()" @endif>
                @foreach($banners as $index => $banner)
                    @php $tag = $banner['link'] ? 'a' : 'div'; @endphp
                    <{{ $tag }}
                        @if($banner['link']) href="{{ $banner['link'] }}" @if($banner['new_tab']) target="_blank" rel="noopener" @endif @endif
                        class="group relative block overflow-hidden bg-gray-100 dark:bg-white/5 {{ $rounded ? 'rounded-2xl lg:rounded-[2rem]' : '' }} {{ $banner['link'] ? 'focus:outline-none focus-visible:ring-4 ring-brown-600/40' : '' }}"
                        @if($ratio !== 'auto') style="aspect-ratio: {{ $ratio }}" @endif
                        wire:key="{{ $uid }}-{{ $index }}"
                    >
                        <picture>
                            @if($banner['mobile'])
                                <source media="(max-width: 767px)" srcset="{{ $banner['mobile'] }}">
                            @endif
                            <img src="{{ $banner['desktop'] }}" alt="{{ $banner['alt'] }}"
                                 loading="{{ $index === 0 ? 'eager' : 'lazy' }}" decoding="async"
                                 class="w-full {{ $ratio !== 'auto' ? 'h-full object-cover' : 'h-auto' }} {{ $zoom ? 'transition-transform duration-700 ease-out group-hover:scale-105' : '' }}">
                        </picture>

                        @if($showText && ($banner['title'] !== '' || $banner['subtitle'] !== ''))
                            <div class="absolute inset-x-0 bottom-0 p-4 lg:p-7 bg-gradient-to-t from-black/70 via-black/25 to-transparent text-white pointer-events-none">
                                @if($banner['title'] !== '')
                                    <h3 class="text-base lg:text-2xl font-black leading-snug drop-shadow">{{ $banner['title'] }}</h3>
                                @endif
                                @if($banner['subtitle'] !== '')
                                    <p class="text-xs lg:text-sm font-bold text-white/85 mt-1 line-clamp-2">{{ $banner['subtitle'] }}</p>
                                @endif
                                @if($banner['link'])
                                    <span class="inline-flex items-center gap-1 mt-2 text-xs font-black text-white/90 group-hover:gap-2 transition-all">
                                        مشاهده
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
                                    </span>
                                @endif
                            </div>
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>

            @if($slider)
                <div class="flex items-center justify-center gap-3 mt-4">
                    <button type="button" @click="go(active - 1)" aria-label="بنر قبلی"
                            class="w-9 h-9 rounded-full flex items-center justify-center bg-white/80 dark:bg-white/10 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 hover:bg-brown-600 hover:text-white transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div class="flex items-center gap-1.5">
                        @foreach($banners as $index => $banner)
                            <button type="button" @click="go({{ $index }})" aria-label="بنر {{ $index + 1 }}"
                                    :class="active === {{ $index }} ? 'w-6 bg-brown-600' : 'w-2 bg-gray-300 dark:bg-white/20'"
                                    class="h-2 rounded-full transition-all duration-300"></button>
                        @endforeach
                    </div>
                    <button type="button" @click="go(active + 1)" aria-label="بنر بعدی"
                            class="w-9 h-9 rounded-full flex items-center justify-center bg-white/80 dark:bg-white/10 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 hover:bg-brown-600 hover:text-white transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                </div>
            @endif
        </section>
    @endif
</div>
